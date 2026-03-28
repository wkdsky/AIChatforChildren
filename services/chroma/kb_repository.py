import json
from typing import Dict, List, Optional

import config
from kb_logic import normalize_title_key


class KnowledgeRepository:
    def __init__(self):
        try:
            import pymysql
            from pymysql.cursors import DictCursor
        except ImportError as exc:
            raise RuntimeError("pymysql is required for the knowledge base repository") from exc

        self._pymysql = pymysql
        self._dict_cursor = DictCursor
        self._ensure_schema()

    def _connect(self):
        return self._pymysql.connect(
            host=config.DB_HOST,
            port=config.DB_PORT,
            user=config.DB_USERNAME,
            password=config.DB_PASS,
            database=config.DB_NAME,
            charset="utf8mb4",
            cursorclass=self._dict_cursor,
            autocommit=False,
        )

    def _ensure_schema(self) -> None:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT COUNT(*) AS column_count
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = %s
                      AND TABLE_NAME = 'kb_documents'
                      AND COLUMN_NAME = 'error_message'
                    """,
                    (config.DB_NAME,),
                )
                row = cursor.fetchone() or {}
                if int(row.get("column_count") or 0) == 0:
                    cursor.execute(
                        "ALTER TABLE kb_documents ADD COLUMN error_message TEXT NULL AFTER summary"
                    )
            conn.commit()
        finally:
            conn.close()

    def _decode_document_row(self, row: Optional[Dict[str, object]]) -> Optional[Dict[str, object]]:
        if not row:
            return None
        row["enabled"] = bool(row.get("enabled"))
        row["age_bands"] = self._load_json(row.get("age_bands"), [])
        row["topics"] = self._load_json(row.get("topics"), [])
        row["tags"] = self._load_json(row.get("tags"), [])
        return row

    def _decode_chunk_row(self, row: Optional[Dict[str, object]]) -> Optional[Dict[str, object]]:
        if not row:
            return None
        row["retrieval_enabled"] = bool(row.get("retrieval_enabled"))
        row["age_bands"] = self._load_json(row.get("age_bands"), [])
        row["topics"] = self._load_json(row.get("topics"), [])
        row["heading_path"] = self._load_json(row.get("heading_path"), row.get("heading_path") or "")
        row["confidence"] = float(row.get("confidence") or 0.0)
        return row

    @staticmethod
    def _dump_json(value) -> str:
        return json.dumps(value, ensure_ascii=False)

    @staticmethod
    def _load_json(value, default):
        if value in (None, ""):
            return default
        if isinstance(value, (list, dict)):
            return value
        try:
            return json.loads(value)
        except (TypeError, json.JSONDecodeError):
            return default

    def find_duplicate(self, document_key: str, content_hash: str) -> Optional[Dict[str, object]]:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT * FROM kb_documents
                    WHERE document_key = %s AND content_hash = %s
                      AND parser_status = 'completed'
                      AND indexing_status = 'completed'
                    ORDER BY version DESC
                    LIMIT 1
                    """,
                    (document_key, content_hash),
                )
                return self._decode_document_row(cursor.fetchone())
        finally:
            conn.close()

    def get_latest_version(self, document_key: str) -> int:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT COALESCE(MAX(version), 0) AS max_version FROM kb_documents WHERE document_key = %s",
                    (document_key,),
                )
                row = cursor.fetchone() or {}
                return int(row.get("max_version") or 0)
        finally:
            conn.close()

    def insert_document(self, metadata: Dict[str, object], file_size: int) -> None:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    INSERT INTO kb_documents (
                        doc_id, document_key, title, original_filename, file_ext, mime_type, format,
                        storage_key, file_size, content_hash, version, language, source_org,
                        source_org_confidence, library, library_confidence, audience,
                        audience_confidence, age_bands, age_bands_confidence, safety_visibility,
                        topics, tags, summary, error_message, risk_level, enabled, review_status, parser_status,
                        chunk_status, embedding_status, indexing_status, created_at, updated_at, last_indexed_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s
                    )
                    """,
                    (
                        metadata["doc_id"],
                        metadata["document_key"],
                        metadata["title"],
                        metadata["original_filename"],
                        metadata["file_ext"],
                        metadata["mime_type"],
                        metadata["format"],
                        metadata["storage_key"],
                        file_size,
                        metadata["content_hash"],
                        metadata["version"],
                        metadata["language"],
                        metadata["source_org"],
                        metadata["source_org_confidence"],
                        metadata["library"],
                        metadata["library_confidence"],
                        metadata["audience"],
                        metadata["audience_confidence"],
                        self._dump_json(metadata["age_bands"]),
                        metadata["age_bands_confidence"],
                        metadata["safety_visibility"],
                        self._dump_json(metadata["topics"]),
                        self._dump_json(metadata["tags"]),
                        metadata["summary"],
                        metadata.get("error_message"),
                        metadata["risk_level"],
                        1 if metadata["enabled"] else 0,
                        metadata["review_status"],
                        metadata["parser_status"],
                        metadata["chunk_status"],
                        metadata["embedding_status"],
                        metadata["indexing_status"],
                        metadata["created_at"],
                        metadata["updated_at"],
                        metadata["last_indexed_at"],
                    ),
                )
            conn.commit()
        finally:
            conn.close()

    def update_document(self, doc_id: str, fields: Dict[str, object]) -> None:
        if not fields:
            return

        serialised = {}
        for key, value in fields.items():
            if key in {"age_bands", "topics", "tags"}:
                serialised[key] = self._dump_json(value)
            elif key == "enabled":
                serialised[key] = 1 if value else 0
            else:
                serialised[key] = value

        assignments = ", ".join(["{0} = %s".format(key) for key in serialised.keys()])
        values = list(serialised.values())
        values.append(doc_id)

        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE kb_documents SET {0} WHERE doc_id = %s".format(assignments),
                    tuple(values),
                )
            conn.commit()
        finally:
            conn.close()

    def insert_chunks(self, chunks: List[Dict[str, object]]) -> None:
        if not chunks:
            return

        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.executemany(
                    """
                    INSERT INTO kb_chunks (
                        chunk_id, doc_id, chunk_index, heading_path, content, char_count, token_count,
                        age_bands, audience, visibility, topics, risk_level, retrieval_enabled,
                        confidence, chunk_summary, embedding_id, vector_id, created_at, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s
                    )
                    """,
                    [
                        (
                            chunk["chunk_id"],
                            chunk["doc_id"],
                            chunk["chunk_index"],
                            self._dump_json(chunk["heading_path"]),
                            chunk["content"],
                            chunk["char_count"],
                            chunk["token_count"],
                            self._dump_json(chunk["age_bands"]),
                            chunk["audience"],
                            chunk["visibility"],
                            self._dump_json(chunk["topics"]),
                            chunk["risk_level"],
                            1 if chunk["retrieval_enabled"] else 0,
                            chunk["confidence"],
                            chunk.get("chunk_summary"),
                            chunk.get("embedding_id"),
                            chunk.get("vector_id"),
                            chunk["created_at"],
                            chunk["updated_at"],
                        )
                        for chunk in chunks
                    ],
                )
            conn.commit()
        finally:
            conn.close()

    def get_document(self, doc_id: str) -> Optional[Dict[str, object]]:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM kb_documents WHERE doc_id = %s LIMIT 1", (doc_id,))
                return self._decode_document_row(cursor.fetchone())
        finally:
            conn.close()

    def get_chunks_for_doc(self, doc_id: str) -> List[Dict[str, object]]:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT * FROM kb_chunks WHERE doc_id = %s ORDER BY chunk_index ASC",
                    (doc_id,),
                )
                return [self._decode_chunk_row(row) for row in cursor.fetchall()]
        finally:
            conn.close()

    def get_chunk(self, chunk_id: str) -> Optional[Dict[str, object]]:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM kb_chunks WHERE chunk_id = %s LIMIT 1", (chunk_id,))
                return self._decode_chunk_row(cursor.fetchone())
        finally:
            conn.close()

    def list_documents(
        self,
        search: Optional[str] = None,
        audience: Optional[str] = None,
        review_status: Optional[str] = None,
    ) -> List[Dict[str, object]]:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                where_clauses: List[str] = []
                params: List[object] = []

                needle = (search or "").strip()
                if needle:
                    keyword = f"%{needle}%"
                    where_clauses.append(
                        """
                        (
                            LOWER(d.title) LIKE LOWER(%s)
                            OR LOWER(d.original_filename) LIKE LOWER(%s)
                            OR EXISTS (
                                SELECT 1
                                FROM kb_chunks c
                                WHERE c.doc_id = d.doc_id
                                  AND (
                                      LOWER(c.content) LIKE LOWER(%s)
                                      OR LOWER(COALESCE(c.chunk_summary, '')) LIKE LOWER(%s)
                                  )
                            )
                        )
                        """
                    )
                    params.extend([keyword, keyword, keyword, keyword])

                if (audience or "").strip():
                    where_clauses.append("d.audience = %s")
                    params.append((audience or "").strip())

                if (review_status or "").strip():
                    where_clauses.append("d.review_status = %s")
                    params.append((review_status or "").strip())

                where_sql = ""
                if where_clauses:
                    where_sql = "WHERE " + " AND ".join(where_clauses)

                cursor.execute(
                    """
                    SELECT d.*, COALESCE(cc.chunk_count, 0) AS chunk_count
                    FROM kb_documents d
                    LEFT JOIN (
                        SELECT doc_id, COUNT(*) AS chunk_count
                        FROM kb_chunks
                        GROUP BY doc_id
                    ) cc ON cc.doc_id = d.doc_id
                    {where_sql}
                    ORDER BY d.updated_at DESC, d.created_at DESC
                    """.format(where_sql=where_sql),
                    tuple(params),
                )
                rows = cursor.fetchall()
                items = []
                for row in rows:
                    decoded = self._decode_document_row(row)
                    decoded["chunk_count"] = int(row.get("chunk_count") or 0)
                    items.append(decoded)
                return items
        finally:
            conn.close()

    def count_indexed_documents(self) -> int:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT COUNT(*) AS total
                    FROM kb_documents
                    WHERE indexing_status = 'completed'
                    """,
                )
                row = cursor.fetchone() or {}
                return int(row.get("total") or 0)
        finally:
            conn.close()

    def rename_document(self, doc_id: str, title: str, updated_at: str) -> None:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE kb_documents SET title = %s, document_key = %s, updated_at = %s WHERE doc_id = %s",
                    (title, normalize_title_key(title), updated_at, doc_id),
                )
            conn.commit()
        finally:
            conn.close()

    def update_chunk(self, chunk_id: str, fields: Dict[str, object]) -> None:
        if not fields:
            return

        serialised = {}
        for key, value in fields.items():
            if key in {"age_bands", "topics", "heading_path"}:
                serialised[key] = self._dump_json(value)
            elif key == "retrieval_enabled":
                serialised[key] = 1 if value else 0
            else:
                serialised[key] = value

        assignments = ", ".join(["{0} = %s".format(key) for key in serialised.keys()])
        values = list(serialised.values())
        values.append(chunk_id)

        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE kb_chunks SET {0} WHERE chunk_id = %s".format(assignments),
                    tuple(values),
                )
            conn.commit()
        finally:
            conn.close()

    def update_chunks(self, doc_id: str, chunk_ids: List[str], fields: Dict[str, object]) -> int:
        if not chunk_ids or not fields:
            return 0

        serialised = {}
        for key, value in fields.items():
            if key in {"age_bands", "topics", "heading_path"}:
                serialised[key] = self._dump_json(value)
            elif key == "retrieval_enabled":
                serialised[key] = 1 if value else 0
            else:
                serialised[key] = value

        placeholders = ", ".join(["%s"] * len(chunk_ids))
        assignments = ", ".join(["{0} = %s".format(key) for key in serialised.keys()])
        values = list(serialised.values())
        values.extend([doc_id, *chunk_ids])

        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE kb_chunks SET {0} WHERE doc_id = %s AND chunk_id IN ({1})".format(assignments, placeholders),
                    tuple(values),
                )
                affected = int(cursor.rowcount or 0)
            conn.commit()
            return affected
        finally:
            conn.close()

    def delete_chunks_for_doc(self, doc_id: str) -> None:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute("DELETE FROM kb_chunks WHERE doc_id = %s", (doc_id,))
            conn.commit()
        finally:
            conn.close()

    def delete_document(self, doc_id: str) -> None:
        conn = self._connect()
        try:
            with conn.cursor() as cursor:
                cursor.execute("DELETE FROM kb_chunks WHERE doc_id = %s", (doc_id,))
                cursor.execute("DELETE FROM kb_documents WHERE doc_id = %s", (doc_id,))
            conn.commit()
        finally:
            conn.close()
