#!/usr/bin/env python3
"""
Rebuild knowledge-base metadata from files already present in storage/knowledge/uploads.

This script is intended for environment migration or disaster recovery when:
- uploaded source files exist under storage/knowledge/uploads
- Chroma data may or may not exist
- MySQL rows in kb_documents / kb_chunks are missing or incomplete
"""

import argparse
import json
import os
import re
import sys
import uuid
from datetime import datetime, timezone
from typing import Dict, Iterable, Optional, Tuple

import config
from kb_logic import default_title_from_filename, normalize_title_key, sha256_bytes


RECOVERED_FILENAME_RE = re.compile(
    r"^(?P<doc_id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})_v(?P<version>\d+)(?P<ext>\.[^.]+)$"
)


def parse_recovered_filename(filename: str) -> Optional[Tuple[str, int, str]]:
    match = RECOVERED_FILENAME_RE.match(os.path.basename(filename))
    if not match:
        return None
    return (
        match.group("doc_id").lower(),
        int(match.group("version")),
        match.group("ext").lower(),
    )


def stable_doc_id_from_storage_key(storage_key: str) -> str:
    return str(uuid.uuid5(uuid.NAMESPACE_URL, storage_key))


def list_upload_files(upload_dir: str) -> Iterable[str]:
    if not os.path.isdir(upload_dir):
        return []

    files = []
    for root, _, names in os.walk(upload_dir):
        for name in names:
            ext = os.path.splitext(name)[1].lower()
            if ext not in config.ALLOWED_EXTENSIONS:
                continue
            files.append(os.path.join(root, name))
    return sorted(files)


def looks_like_recovered_filename(filename: str) -> bool:
    stem = os.path.splitext(os.path.basename(filename))[0]
    return bool(re.match(r"^[0-9a-fA-F-]{36}_v\d+$", stem))


def extract_title_from_text(text: str) -> str:
    for raw_line in text.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        line = re.sub(r"^#{1,6}\s*", "", line)
        line = re.sub(r"^[0-9]+(?:\.[0-9]+)*\s+", "", line)
        line = re.sub(r"\s+", " ", line).strip()
        if len(line) >= 2:
            return line[:255]
    return ""


def guess_recovered_title(filename: str, extracted_text: str) -> str:
    fallback = default_title_from_filename(filename)[:255]
    if not looks_like_recovered_filename(filename):
        return fallback or "Recovered document"

    inferred = extract_title_from_text(extracted_text)
    if inferred:
        return inferred
    return "Recovered document"


def iso_from_timestamp(timestamp: float) -> str:
    return datetime.fromtimestamp(timestamp, tz=timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def build_file_record(file_path: str) -> Dict[str, object]:
    relative_path = os.path.relpath(file_path, config.STORAGE_DIR)
    filename = os.path.basename(file_path)
    recovered = parse_recovered_filename(filename)
    file_ext = os.path.splitext(filename)[1].lower()
    storage_key = relative_path.replace(os.sep, "/")

    if recovered:
        doc_id, version, _ = recovered
    else:
        doc_id = stable_doc_id_from_storage_key(storage_key)
        version = 1

    stat = os.stat(file_path)
    with open(file_path, "rb") as handle:
        content = handle.read()

    return {
        "doc_id": doc_id,
        "version": version,
        "storage_key": storage_key,
        "filename": filename,
        "file_ext": file_ext,
        "file_size": int(stat.st_size),
        "content_hash": sha256_bytes(content),
        "content": content,
        "created_at": iso_from_timestamp(stat.st_mtime),
    }


def clear_document_vectors(repository, collection, doc_id: str) -> None:
    chunks = repository.get_chunks_for_doc(doc_id)
    vector_ids = [str(chunk.get("vector_id")) for chunk in chunks if chunk.get("vector_id")]
    if vector_ids:
        collection.delete(ids=vector_ids)
    repository.delete_chunks_for_doc(doc_id)


def write_status(status_file: Optional[str], payload: Dict[str, object]) -> None:
    if not status_file:
        return

    os.makedirs(os.path.dirname(status_file), exist_ok=True)
    with open(status_file, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, ensure_ascii=False, indent=2)


def update_status(status_file: Optional[str], **fields: object) -> None:
    base = {
        "status": "idle",
        "message": "",
        "started_at": None,
        "finished_at": None,
        "updated_at": iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp()),
        "scanned": 0,
        "inserted": 0,
        "repaired": 0,
        "skipped": 0,
        "failed": 0,
        "last_file": None,
    }
    if status_file and os.path.exists(status_file):
        try:
            with open(status_file, "r", encoding="utf-8") as handle:
                loaded = json.load(handle)
                if isinstance(loaded, dict):
                    base.update(loaded)
        except (OSError, json.JSONDecodeError):
            pass

    base.update(fields)
    base["updated_at"] = iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp())
    write_status(status_file, base)


def rebuild(args: argparse.Namespace) -> int:
    from main import (
        KnowledgeIngestionPipeline,
        build_pending_document_metadata,
        extract_text_from_file,
        get_repository,
        collection,
    )

    upload_files = list(list_upload_files(config.UPLOAD_DIR))
    if not upload_files:
        update_status(
            args.status_file,
            status="completed",
            message="No upload files found.",
            started_at=iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp()),
            finished_at=iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp()),
            scanned=0,
            inserted=0,
            repaired=0,
            skipped=0,
            failed=0,
        )
        print("No upload files found under {0}".format(config.UPLOAD_DIR))
        return 0

    repository = get_repository()
    pipeline = KnowledgeIngestionPipeline(repository)

    inserted = 0
    repaired = 0
    skipped = 0
    failed = 0
    started_at = iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp())

    update_status(
        args.status_file,
        status="running",
        message="Scanning upload files.",
        started_at=started_at,
        finished_at=None,
        scanned=len(upload_files),
        inserted=0,
        repaired=0,
        skipped=0,
        failed=0,
        last_file=None,
    )

    print("Scanning {0} upload file(s) in {1}".format(len(upload_files), config.UPLOAD_DIR))

    for file_path in upload_files:
        record = build_file_record(file_path)
        doc_id = str(record["doc_id"])
        file_format = config.FORMAT_BY_EXT.get(str(record["file_ext"]), "other")
        mime_type = config.guess_mime_type(str(record["filename"]))
        existing = repository.get_document(doc_id)

        parsed_text = ""
        try:
            if file_format != "other":
                parsed_text = extract_text_from_file(file_path, file_format)
        except Exception:
            parsed_text = ""

        recovered_title = guess_recovered_title(str(record["filename"]), parsed_text)
        effective_title = str(existing.get("title") or "").strip() if existing else ""
        if not effective_title:
            effective_title = recovered_title

        original_filename = str(existing.get("original_filename") or "").strip() if existing else ""
        if not original_filename:
            original_filename = str(record["filename"])

        is_complete = bool(
            existing
            and str(existing.get("parser_status")) == config.STATUS_COMPLETED
            and str(existing.get("indexing_status")) == config.STATUS_COMPLETED
            and str(existing.get("content_hash") or "") == str(record["content_hash"])
        )

        if is_complete and not args.force:
            skipped += 1
            update_status(
                args.status_file,
                status="running",
                message="Skipping already indexed document.",
                scanned=len(upload_files),
                inserted=inserted,
                repaired=repaired,
                skipped=skipped,
                failed=failed,
                last_file=effective_title,
            )
            print("[skip] {0} ({1})".format(doc_id, effective_title))
            continue

        if args.dry_run:
            action = "repair" if existing else "insert"
            update_status(
                args.status_file,
                status="running",
                message="Dry run: {0}".format("repairing" if existing else "inserting"),
                scanned=len(upload_files),
                inserted=inserted + (0 if existing else 1),
                repaired=repaired + (1 if existing else 0),
                skipped=skipped,
                failed=failed,
                last_file=effective_title,
            )
            print("[dry-run:{0}] {1} -> {2}".format(action, doc_id, effective_title))
            if existing:
                repaired += 1
            else:
                inserted += 1
            continue

        try:
            pending = build_pending_document_metadata(
                doc_id=doc_id,
                title=effective_title,
                original_filename=original_filename,
                file_ext=str(record["file_ext"]),
                mime_type=mime_type,
                file_format=file_format,
                storage_key=str(record["storage_key"]),
                content_hash=str(record["content_hash"]),
                version=int(record["version"]),
            )
            pending["created_at"] = str(existing.get("created_at") or record["created_at"]) if existing else str(record["created_at"])
            pending["updated_at"] = str(record["created_at"])

            if existing:
                clear_document_vectors(repository, collection, doc_id)
                repository.update_document(
                    doc_id,
                    {
                        "document_key": normalize_title_key(effective_title),
                        "title": effective_title,
                        "original_filename": original_filename,
                        "file_ext": str(record["file_ext"]),
                        "mime_type": mime_type,
                        "format": file_format,
                        "storage_key": str(record["storage_key"]),
                        "file_size": int(record["file_size"]),
                        "content_hash": str(record["content_hash"]),
                        "version": int(record["version"]),
                        "enabled": True,
                        "review_status": "needs_review",
                        "parser_status": config.STATUS_PENDING,
                        "chunk_status": config.STATUS_PENDING,
                        "embedding_status": config.STATUS_PENDING,
                        "indexing_status": config.STATUS_PENDING,
                        "error_message": None,
                        "updated_at": str(record["created_at"]),
                        "last_indexed_at": None,
                    },
                )
                pipeline.process_pending_upload(doc_id)
                current = repository.get_document(doc_id) or {}
                if str(current.get("indexing_status")) == config.STATUS_COMPLETED:
                    repaired += 1
                    update_status(
                        args.status_file,
                        status="running",
                        message="Document repaired successfully.",
                        scanned=len(upload_files),
                        inserted=inserted,
                        repaired=repaired,
                        skipped=skipped,
                        failed=failed,
                        last_file=effective_title,
                    )
                    print("[repair] {0} ({1})".format(doc_id, effective_title))
                else:
                    failed += 1
                    update_status(
                        args.status_file,
                        status="running",
                        message="Document rebuild failed.",
                        scanned=len(upload_files),
                        inserted=inserted,
                        repaired=repaired,
                        skipped=skipped,
                        failed=failed,
                        last_file=effective_title,
                    )
                    print(
                        "[failed] {0} ({1}): {2}".format(
                            doc_id,
                            effective_title,
                            current.get("error_message") or "indexing did not complete",
                        ),
                        file=sys.stderr,
                    )
            else:
                repository.insert_document(pending, int(record["file_size"]))
                pipeline.process_pending_upload(doc_id)
                current = repository.get_document(doc_id) or {}
                if str(current.get("indexing_status")) == config.STATUS_COMPLETED:
                    inserted += 1
                    update_status(
                        args.status_file,
                        status="running",
                        message="Document restored successfully.",
                        scanned=len(upload_files),
                        inserted=inserted,
                        repaired=repaired,
                        skipped=skipped,
                        failed=failed,
                        last_file=effective_title,
                    )
                    print("[insert] {0} ({1})".format(doc_id, effective_title))
                else:
                    failed += 1
                    update_status(
                        args.status_file,
                        status="running",
                        message="Document restore failed.",
                        scanned=len(upload_files),
                        inserted=inserted,
                        repaired=repaired,
                        skipped=skipped,
                        failed=failed,
                        last_file=effective_title,
                    )
                    print(
                        "[failed] {0} ({1}): {2}".format(
                            doc_id,
                            effective_title,
                            current.get("error_message") or "indexing did not complete",
                        ),
                        file=sys.stderr,
                    )
        except Exception as exc:
            failed += 1
            update_status(
                args.status_file,
                status="running",
                message="Document processing failed.",
                scanned=len(upload_files),
                inserted=inserted,
                repaired=repaired,
                skipped=skipped,
                failed=failed,
                last_file=effective_title,
            )
            print("[failed] {0} ({1}): {2}".format(doc_id, effective_title, exc), file=sys.stderr)

    finished_at = iso_from_timestamp(datetime.now(tz=timezone.utc).timestamp())
    update_status(
        args.status_file,
        status="failed" if failed else "completed",
        message="Finished with failures." if failed else "Rebuild completed successfully.",
        started_at=started_at,
        finished_at=finished_at,
        scanned=len(upload_files),
        inserted=inserted,
        repaired=repaired,
        skipped=skipped,
        failed=failed,
    )
    print(
        "Finished. inserted={0} repaired={1} skipped={2} failed={3}".format(
            inserted,
            repaired,
            skipped,
            failed,
        )
    )
    return 1 if failed else 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Rebuild kb_documents/kb_chunks from files in storage/knowledge/uploads",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Reprocess files even when the matching document already looks complete.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Show what would be rebuilt without writing MySQL or Chroma data.",
    )
    parser.add_argument(
        "--status-file",
        default="",
        help="Optional JSON status file path used by the admin page to monitor progress.",
    )
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    try:
        return rebuild(args)
    except KeyboardInterrupt:
        print("Interrupted.", file=sys.stderr)
        return 130
    except Exception as exc:
        print("Rebuild failed: {0}".format(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
