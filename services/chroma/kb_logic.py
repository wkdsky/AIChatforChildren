import hashlib
import os
import re
from datetime import datetime
from typing import Dict, List, Optional, Set, Tuple


CONTROLLED_TOPICS = {
    "milestones": [
        "milestone", "developmental milestone", "growth", "speech development",
        "motor skill", "cognitive development", "里程碑", "发育", "成长",
    ],
    "emotion": [
        "emotion", "feelings", "anxiety", "stress", "self-esteem", "情绪", "感受", "焦虑",
    ],
    "play": [
        "play", "game", "activity", "story", "worksheet", "craft", "drawing", "游戏", "活动", "故事",
    ],
    "parenting": [
        "parent", "caregiver", "family", "caregiving", "家长", "监护人", "家庭", "育儿",
    ],
    "online_safety": [
        "online safety", "internet safety", "cyber", "privacy", "grooming", "网络安全", "上网安全", "隐私",
    ],
    "media_use": [
        "screen time", "media", "device", "video", "social media", "媒介", "屏幕", "设备",
    ],
    "school": [
        "school", "classroom", "teacher", "homework", "课室", "学校", "课堂", "作业",
    ],
    "sleep": [
        "sleep", "bedtime", "nap", "睡眠", "作息", "午睡",
    ],
    "health": [
        "health", "nutrition", "exercise", "well-being", "doctor", "医院", "健康", "营养",
    ],
    "social": [
        "friend", "peer", "bullying", "relationship", "social", "朋友", "社交", "同伴", "霸凌",
    ],
    "learning": [
        "learn", "reading", "math", "literacy", "education", "学习", "阅读", "教育",
    ],
    "crisis": [
        "crisis", "abuse", "suicide", "self-harm", "violence", "trauma", "报警", "伤害", "危机",
    ],
}

SOURCE_ORG_PATTERNS = {
    "UNICEF": [r"\bunicef\b", r"联合国儿童基金会"],
    "WHO": [r"\bwho\b", r"world health organization", r"世界卫生组织"],
    "CDC": [r"\bcdc\b", r"centers for disease control", r"疾控"],
    "AAP": [r"\baap\b", r"american academy of pediatrics", r"美国儿科学会"],
    "NSPCC": [r"\bnspcc\b"],
    "ITU": [r"\bitu\b", r"international telecommunication union"],
    "Common Sense": [r"common sense", r"common sense media"],
}

LIBRARY_KEYWORDS = {
    "rules": [
        "policy", "guidance", "framework", "safety", "governance", "risk", "moderation",
        "规范", "原则", "平台职责", "治理", "风控", "策略",
    ],
    "parent": [
        "parent", "caregiver", "family", "care plan", "tips", "home routine",
        "家长", "监护人", "家庭协议", "父母", "建议",
    ],
    "age_content": [
        "activity", "story", "play", "worksheet", "teen guide", "child guide",
        "游戏", "故事", "互动", "活动", "工作纸", "练习",
    ],
}

AUDIENCE_KEYWORDS = {
    "system": [
        "policy", "platform", "moderation", "governance", "compliance", "风控", "治理", "平台",
    ],
    "parent": [
        "parent", "caregiver", "family", "guardian", "家长", "监护人", "家庭",
    ],
    "child": [
        "kids", "children", "child", "teen", "student", "let's", "you can try",
        "小朋友", "儿童", "青少年", "你可以", "一起来",
    ],
    "teacher": [
        "teacher", "classroom", "lesson", "educator", "课堂", "教师", "老师",
    ],
}

RISK_KEYWORDS = {
    "high": [
        "suicide", "self-harm", "sexual abuse", "rape", "emergency", "violence", "crisis",
        "自残", "自杀", "性侵", "虐待", "危机", "报警",
    ],
    "medium": [
        "privacy", "stranger", "bullying", "conflict", "harassment", "online risk",
        "隐私", "陌生人", "霸凌", "冲突", "骚扰", "网络风险",
    ],
}

AGE_PATTERNS = {
    "0_3": [
        r"\b0[\s\-to]{0,4}3\b", r"\bunder 3\b", r"\binfant", r"\btoddler", r"\bbaby",
        r"0-3岁", r"婴儿", r"幼儿",
    ],
    "3_6": [
        r"\b3[\s\-to]{0,4}6\b", r"\bpreschool", r"\bkindergarten", r"\bunder 5\b",
        r"3-6岁", r"学龄前", r"幼儿园",
    ],
    "6_12": [
        r"\b6[\s\-to]{0,4}12\b", r"\belementary", r"\bprimary school", r"\bschool age",
        r"6-12岁", r"小学", r"学龄儿童",
    ],
    "12_18": [
        r"\b12[\s\-to]{0,4}18\b", r"\bteen", r"\bteenager", r"\badolescent", r"\byouth",
        r"12-18岁", r"青少年",
    ],
}

AGE_SEMANTIC_HINTS = {
    "0_3": ["baby", "toddler", "soothe", "nap", "喂养", "安抚"],
    "3_6": ["preschool", "pretend play", "picture book", "颜色", "拼图"],
    "6_12": ["school", "friend", "homework", "reading", "课堂", "同学"],
    "12_18": ["teen", "identity", "puberty", "social media", "adolescent", "青春期"],
}

AGE_FIELDS = ["all", "0_3", "3_6", "6_12", "12_18"]
SESSION_TYPES = {"child", "parent", "system"}
QUERY_TOPIC_KEYWORDS = {
    "sleep": ["sleep", "bedtime", "nap", "wake", "night", "睡眠", "作息", "午睡", "入睡"],
    "emotion": ["emotion", "emotional", "feel", "feeling", "sad", "angry", "anxiety", "stress", "情绪", "难过", "生气", "焦虑"],
    "school": ["school", "homework", "class", "teacher", "classroom", "课", "学校", "作业", "老师", "课堂"],
    "online_safety": ["online", "internet", "privacy", "stranger", "cyber", "screen", "social media", "网络", "隐私", "陌生人", "上网"],
}
QUERY_STOPWORDS = {
    "the", "a", "an", "is", "are", "to", "for", "of", "on", "in", "and", "or",
    "how", "what", "why", "can", "i", "me", "my", "we", "our", "with", "about",
    "儿童", "孩子", "小朋友", "关于",
}


def now_iso() -> str:
    return datetime.utcnow().replace(microsecond=0).isoformat() + "Z"


def default_title_from_filename(filename: str) -> str:
    stem = os.path.splitext(os.path.basename(filename))[0].strip()
    return stem or "Untitled"


def normalize_title_key(title: str) -> str:
    normalized = re.sub(r"[^\w\u4e00-\u9fff]+", "-", title.lower(), flags=re.U)
    normalized = normalized.replace("_", "-")
    normalized = re.sub(r"-{2,}", "-", normalized)
    normalized = normalized.strip("-")
    return normalized or "untitled"


def sha256_bytes(content: bytes) -> str:
    return hashlib.sha256(content).hexdigest()


def normalize_text(text: str) -> str:
    return re.sub(r"\s+", " ", text or "").strip()


def tokenize_query(text: str) -> List[str]:
    normalized = normalize_text(text).lower()
    if not normalized:
        return []

    english_tokens = re.findall(r"[a-z0-9_]+", normalized)
    chinese_tokens = re.findall(r"[\u4e00-\u9fff]{2,}", normalized)
    tokens = english_tokens + chinese_tokens

    unique = []
    for token in tokens:
        if token in QUERY_STOPWORDS:
            continue
        if len(token) <= 1:
            continue
        if token not in unique:
            unique.append(token)
    return unique


def map_query_topics(query: str) -> List[str]:
    lowered = normalize_text(query).lower()
    if not lowered:
        return []

    matched = []
    for topic, keywords in QUERY_TOPIC_KEYWORDS.items():
        for keyword in keywords:
            if keyword.lower() in lowered:
                matched.append(topic)
                break

    return matched


def compute_keyword_overlap(query_terms: List[str], texts: List[str]) -> float:
    if not query_terms:
        return 0.0

    scope = " ".join([normalize_text(text).lower() for text in texts if text]).strip()
    if not scope:
        return 0.0

    matched: Set[str] = set()
    for term in query_terms:
        if term in scope:
            matched.add(term)

    return round(len(matched) / max(1, len(query_terms)), 4)


def clean_html(raw_html: str) -> str:
    text = re.sub(r"<(script|style)\b[^>]*>.*?</\1>", " ", raw_html, flags=re.I | re.S)
    text = re.sub(r"<br\s*/?>", "\n", text, flags=re.I)
    text = re.sub(r"</(p|div|h[1-6]|li)>", "\n", text, flags=re.I)
    text = re.sub(r"<[^>]+>", " ", text)
    text = re.sub(r"&nbsp;", " ", text, flags=re.I)
    text = re.sub(r"&amp;", "&", text, flags=re.I)
    return normalize_text(text.replace("\r", "\n"))


def split_sentences(text: str) -> List[str]:
    raw = re.split(r"(?<=[。！？.!?])\s+", text)
    return [item.strip() for item in raw if item.strip()]


def summarize_text(text: str, max_length: int = 220) -> str:
    sentences = split_sentences(text)
    if sentences:
        summary = " ".join(sentences[:2]).strip()
    else:
        summary = normalize_text(text[:max_length])

    if len(summary) <= max_length:
        return summary

    return summary[: max_length - 3].rstrip() + "..."


def detect_language(text: str) -> str:
    if not text:
        return "other"

    zh_count = len(re.findall(r"[\u4e00-\u9fff]", text))
    latin_count = len(re.findall(r"[A-Za-z]", text))
    total = zh_count + latin_count

    if total == 0:
        return "other"

    zh_ratio = zh_count / total
    latin_ratio = latin_count / total

    if zh_ratio >= 0.7:
        return "zh"
    if latin_ratio >= 0.7:
        return "en"
    if zh_count > 0 and latin_count > 0:
        return "mixed"
    return "other"


def _score_by_patterns(text: str, patterns: List[str]) -> int:
    score = 0
    for pattern in patterns:
        if re.search(pattern, text, flags=re.I):
            score += 1
    return score


def infer_source_org(title: str, filename: str, text: str) -> Tuple[str, float]:
    title_scope = f"{title}\n{filename}"
    early_text = text[:4000]
    best_label = "Unknown"
    best_score = 0

    for label, patterns in SOURCE_ORG_PATTERNS.items():
        score = (_score_by_patterns(title_scope, patterns) * 4) + (_score_by_patterns(early_text, patterns) * 2)
        if score > best_score:
            best_score = score
            best_label = label

    if best_score <= 0:
        return "Unknown", 0.2

    confidence = min(0.99, 0.45 + (best_score * 0.08))
    return best_label, round(confidence, 4)


def _keyword_score(text: str, keywords: List[str]) -> int:
    lowered = text.lower()
    score = 0
    for keyword in keywords:
        keyword_lower = keyword.lower()
        if keyword_lower in lowered:
            score += max(1, lowered.count(keyword_lower))
    return score


def _pick_label(scores: Dict[str, int], allow_mixed: bool = True) -> Tuple[str, float]:
    ordered = sorted(scores.items(), key=lambda item: item[1], reverse=True)
    if not ordered or ordered[0][1] <= 0:
        return ("mixed" if allow_mixed else ""), 0.35

    top_label, top_score = ordered[0]
    second_score = ordered[1][1] if len(ordered) > 1 else 0
    total = max(1, sum(scores.values()))

    if allow_mixed and second_score > 0 and abs(top_score - second_score) <= 1:
        return "mixed", round(min(0.85, top_score / total), 4)

    confidence = min(0.99, max(0.45, top_score / total))
    return top_label, round(confidence, 4)


def classify_library(title: str, text: str) -> Tuple[str, float, Dict[str, int]]:
    scope = f"{title}\n{text[:6000]}"
    scores = {
        label: _keyword_score(scope, keywords)
        for label, keywords in LIBRARY_KEYWORDS.items()
    }
    label, confidence = _pick_label(scores, allow_mixed=True)
    if label == "":
        label = "mixed"
    return label, confidence, scores


def classify_audience(title: str, text: str, allow_mixed: bool = True) -> Tuple[str, float, Dict[str, int]]:
    scope = f"{title}\n{text[:6000]}"
    scores = {
        label: _keyword_score(scope, keywords)
        for label, keywords in AUDIENCE_KEYWORDS.items()
    }
    label, confidence = _pick_label(scores, allow_mixed=allow_mixed)
    if not label:
        label = "child"
    return label, confidence, scores


def infer_age_bands(title: str, text: str, library: str, audience: str) -> Tuple[List[str], float]:
    scope = f"{title}\n{text[:8000]}"
    matched = []

    for age_band, patterns in AGE_PATTERNS.items():
        if _score_by_patterns(scope, patterns) > 0:
            matched.append(age_band)

    if matched:
        return matched, 0.9

    semantic_scores = {
        age_band: _keyword_score(scope, hints)
        for age_band, hints in AGE_SEMANTIC_HINTS.items()
    }
    ranked = sorted(semantic_scores.items(), key=lambda item: item[1], reverse=True)
    if ranked and ranked[0][1] > 0:
        if len(ranked) > 1 and ranked[1][1] > 0 and abs(ranked[0][1] - ranked[1][1]) <= 1:
            matched = sorted([ranked[0][0], ranked[1][0]])
            return matched, 0.62
        return [ranked[0][0]], 0.68

    if library in {"rules", "parent"} or audience in {"system", "parent", "teacher"}:
        return ["all"], 0.7

    return ["6_12"], 0.45


def extract_topics(text: str) -> List[str]:
    lowered = text.lower()
    topics = []
    for topic, keywords in CONTROLLED_TOPICS.items():
        if any(keyword.lower() in lowered for keyword in keywords):
            topics.append(topic)
    return topics


def extract_tags(title: str, text: str) -> List[str]:
    scope = f"{title} {text[:3000]}".lower()
    tags = []
    tag_patterns = {
        "digital-wellbeing": ["screen time", "digital wellbeing", "数字健康"],
        "bullying": ["bullying", "霸凌"],
        "family-routine": ["routine", "作息", "home routine"],
        "storytelling": ["story", "故事"],
    }
    for tag, keywords in tag_patterns.items():
        if any(keyword in scope for keyword in keywords):
            tags.append(tag)
    return tags


def infer_risk_level(text: str, topics: List[str]) -> str:
    scope = text.lower()
    if any(keyword.lower() in scope for keyword in RISK_KEYWORDS["high"]) or "crisis" in topics:
        return "high"
    if any(keyword.lower() in scope for keyword in RISK_KEYWORDS["medium"]) or "online_safety" in topics:
        return "medium"
    return "low"


def infer_document_visibility(library: str, audience: str, risk_level: str) -> str:
    if library == "rules" or audience == "system":
        return "system_only"
    if audience in {"parent", "teacher"}:
        return "parent_only"
    if library == "mixed" or audience == "mixed":
        return "mixed"
    if audience == "child" and risk_level in {"low", "medium"}:
        return "retrieval_visible"
    return "mixed"


def infer_review_status(
    parser_status: str,
    library_confidence: float,
    audience_confidence: float,
    age_confidence: float,
    risk_level: str,
    visibility: str,
) -> str:
    if parser_status != "completed":
        return "blocked"

    min_confidence = min(library_confidence, audience_confidence, age_confidence)
    if risk_level == "high" and min_confidence < 0.5:
        return "blocked"
    if visibility == "mixed" or min_confidence < 0.6:
        return "needs_review"
    return "auto_accepted"


def build_document_metadata(
    doc_id: str,
    title: str,
    original_filename: str,
    file_ext: str,
    mime_type: str,
    file_format: str,
    storage_key: str,
    content_hash: str,
    version: int,
    text: str,
) -> Dict[str, object]:
    language = detect_language(text)
    source_org, source_org_confidence = infer_source_org(title, original_filename, text)
    library, library_confidence, _ = classify_library(title, text)
    audience, audience_confidence, _ = classify_audience(title, text, allow_mixed=True)
    age_bands, age_confidence = infer_age_bands(title, text, library, audience)
    topics = extract_topics(text)
    tags = extract_tags(title, text)
    risk_level = infer_risk_level(text, topics)
    safety_visibility = infer_document_visibility(library, audience, risk_level)
    parser_status = "completed"
    review_status = infer_review_status(
        parser_status,
        library_confidence,
        audience_confidence,
        age_confidence,
        risk_level,
        safety_visibility,
    )
    enabled = parser_status == "completed"
    now = now_iso()

    return {
        "doc_id": doc_id,
        "title": title,
        "document_key": normalize_title_key(title),
        "original_filename": original_filename,
        "file_ext": file_ext,
        "mime_type": mime_type,
        "format": file_format,
        "storage_key": storage_key,
        "content_hash": content_hash,
        "version": version,
        "language": language,
        "source_org": source_org,
        "source_org_confidence": source_org_confidence,
        "library": library,
        "library_confidence": library_confidence,
        "audience": audience,
        "audience_confidence": audience_confidence,
        "age_bands": age_bands,
        "age_bands_confidence": age_confidence,
        "safety_visibility": safety_visibility,
        "topics": topics,
        "tags": tags,
        "summary": summarize_text(text),
        "risk_level": risk_level,
        "enabled": enabled,
        "review_status": review_status,
        "parser_status": parser_status,
        "chunk_status": "pending",
        "embedding_status": "pending",
        "indexing_status": "pending",
        "created_at": now,
        "updated_at": now,
        "last_indexed_at": None,
    }


def chunk_text(text: str, chunk_size: int, overlap: int) -> List[str]:
    normalized = text.strip()
    if not normalized:
        return []

    chunks = []
    start = 0
    length = len(normalized)

    while start < length:
        end = min(length, start + chunk_size)
        chunk = normalized[start:end]
        if end < length:
            boundary = max(chunk.rfind("\n"), chunk.rfind("。"), chunk.rfind("."), chunk.rfind("!"), chunk.rfind("?"))
            if boundary > chunk_size // 2:
                end = start + boundary + 1
                chunk = normalized[start:end]
        chunks.append(chunk.strip())
        if end >= length:
            break
        start = max(0, end - overlap)

    return [item for item in chunks if item]


def split_sections(text: str) -> List[Tuple[str, str]]:
    lines = text.splitlines()
    sections = []
    current_heading = "Root"
    buffer = []

    for raw_line in lines:
        line = raw_line.strip()
        if not line:
            buffer.append("")
            continue

        is_heading = bool(re.match(r"^(#{1,6}\s+.+|[0-9]+(\.[0-9]+)*\s+.+)$", line))
        if is_heading:
            if buffer:
                sections.append((current_heading, "\n".join(buffer).strip()))
                buffer = []
            current_heading = re.sub(r"^#{1,6}\s*", "", line).strip()
            continue

        buffer.append(line)

    if buffer:
        sections.append((current_heading, "\n".join(buffer).strip()))

    return [(heading, content) for heading, content in sections if content]


def chunk_document(text: str, chunk_size: int, overlap: int) -> List[Dict[str, object]]:
    sections = split_sections(text)
    if not sections:
        sections = [("Root", text)]

    chunks = []
    chunk_index = 0
    for heading, content in sections:
        for piece in chunk_text(content, chunk_size, overlap):
            chunks.append(
                {
                    "chunk_index": chunk_index,
                    "heading_path": heading,
                    "content": piece,
                    "char_count": len(piece),
                    "token_count": estimate_token_count(piece),
                }
            )
            chunk_index += 1
    return chunks


def estimate_token_count(text: str) -> int:
    return max(1, int(len(text) / 4))


def _single_audience_from_scores(scores: Dict[str, int]) -> Tuple[str, float]:
    ordered = sorted(scores.items(), key=lambda item: item[1], reverse=True)
    if not ordered or ordered[0][1] <= 0:
        return "child", 0.4
    total = max(1, sum(scores.values()))
    return ordered[0][0], round(min(0.99, max(0.4, ordered[0][1] / total)), 4)


def classify_chunk(chunk: Dict[str, object], document_metadata: Dict[str, object]) -> Dict[str, object]:
    content = str(chunk["content"])
    audience_guess, audience_confidence, audience_scores = classify_audience(
        str(document_metadata["title"]),
        content,
        allow_mixed=False,
    )
    if audience_guess == "":
        audience_guess, audience_confidence = _single_audience_from_scores(audience_scores)

    if audience_guess not in {"system", "parent", "child", "teacher"}:
        inherited = str(document_metadata.get("audience") or "child")
        audience_guess = inherited if inherited in {"system", "parent", "child", "teacher"} else "child"
        audience_confidence = min(audience_confidence, 0.55)

    age_bands, age_confidence = infer_age_bands(
        str(document_metadata["title"]),
        content,
        str(document_metadata["library"]),
        audience_guess,
    )
    topics = extract_topics(content)
    risk_level = infer_risk_level(content, topics)
    confidence = round((audience_confidence + age_confidence) / 2, 4)

    if audience_guess == "system":
        visibility = "system_only"
    elif audience_guess in {"parent", "teacher"}:
        visibility = "parent_only"
    elif risk_level == "high" and confidence < 0.65:
        visibility = "blocked"
    else:
        visibility = "retrieval_visible"

    retrieval_enabled = bool(document_metadata.get("enabled", False))
    if visibility == "blocked":
        retrieval_enabled = False
    if risk_level == "high" and audience_guess == "child":
        retrieval_enabled = False

    now = now_iso()
    chunk_id = "{0}_chunk_{1}".format(document_metadata["doc_id"], chunk["chunk_index"])

    return {
        "chunk_id": chunk_id,
        "doc_id": document_metadata["doc_id"],
        "chunk_index": chunk["chunk_index"],
        "heading_path": chunk["heading_path"],
        "content": content,
        "char_count": chunk["char_count"],
        "token_count": chunk["token_count"],
        "age_bands": age_bands,
        "audience": audience_guess,
        "visibility": visibility,
        "topics": topics,
        "risk_level": risk_level,
        "retrieval_enabled": retrieval_enabled,
        "confidence": confidence,
        "chunk_summary": summarize_text(content, max_length=160),
        "embedding_id": chunk_id,
        "vector_id": chunk_id,
        "created_at": now,
        "updated_at": now,
    }


def classify_chunks(chunks: List[Dict[str, object]], document_metadata: Dict[str, object]) -> List[Dict[str, object]]:
    return [classify_chunk(chunk, document_metadata) for chunk in chunks]


def build_vector_metadata(document_metadata: Dict[str, object], chunk_metadata: Dict[str, object]) -> Dict[str, object]:
    age_bands = set(chunk_metadata.get("age_bands", []))
    topics = set(chunk_metadata.get("topics", []))
    flat = {
        "doc_id": document_metadata["doc_id"],
        "chunk_id": chunk_metadata["chunk_id"],
        "enabled": 1 if document_metadata.get("enabled") else 0,
        "retrieval_enabled": 1 if chunk_metadata.get("retrieval_enabled") else 0,
        "library": document_metadata.get("library", "mixed"),
        "audience": chunk_metadata.get("audience", "child"),
        "visibility": chunk_metadata.get("visibility", "blocked"),
        "risk_level": chunk_metadata.get("risk_level", "low"),
        "review_status": document_metadata.get("review_status", "needs_review"),
        "language": document_metadata.get("language", "other"),
        "age_all": 1 if "all" in age_bands else 0,
        "age_0_3": 1 if "0_3" in age_bands else 0,
        "age_3_6": 1 if "3_6" in age_bands else 0,
        "age_6_12": 1 if "6_12" in age_bands else 0,
        "age_12_18": 1 if "12_18" in age_bands else 0,
        "topic_milestones": 1 if "milestones" in topics else 0,
        "topic_emotion": 1 if "emotion" in topics else 0,
        "topic_play": 1 if "play" in topics else 0,
        "topic_parenting": 1 if "parenting" in topics else 0,
        "topic_online_safety": 1 if "online_safety" in topics else 0,
        "topic_media_use": 1 if "media_use" in topics else 0,
        "topic_school": 1 if "school" in topics else 0,
        "topic_sleep": 1 if "sleep" in topics else 0,
        "topic_health": 1 if "health" in topics else 0,
        "topic_social": 1 if "social" in topics else 0,
        "topic_learning": 1 if "learning" in topics else 0,
        "topic_crisis": 1 if "crisis" in topics else 0,
        "title": document_metadata.get("title", ""),
        "original_filename": document_metadata.get("original_filename", ""),
        "source_org": document_metadata.get("source_org", "Unknown"),
        "chunk_index": chunk_metadata.get("chunk_index", 0),
        "confidence": float(chunk_metadata.get("confidence", 0.0)),
    }
    return flat


def build_vector_filter(session_type: str, age_band: Optional[str] = None) -> Dict[str, object]:
    conditions = [{"enabled": 1}, {"retrieval_enabled": 1}]

    if session_type == "child":
        conditions.append({"visibility": "retrieval_visible"})
        # When child age is unknown, do not apply age filtering.
        if age_band in {"0_3", "3_6", "6_12", "12_18"}:
            conditions.append({"$or": [{"age_all": 1}, {f"age_{age_band}": 1}]})
    elif session_type == "parent":
        conditions.append({"$or": [{"visibility": "parent_only"}, {"visibility": "retrieval_visible"}]})
    else:
        conditions.append({"$or": [{"visibility": "system_only"}, {"visibility": "parent_only"}, {"visibility": "retrieval_visible"}]})

    return {"$and": conditions}


def chunk_matches_session(metadata: Dict[str, object], session_type: str, age_band: Optional[str] = None) -> bool:
    if int(metadata.get("enabled", 0)) != 1 or int(metadata.get("retrieval_enabled", 0)) != 1:
        return False

    visibility = str(metadata.get("visibility", "blocked"))
    if session_type == "child":
        if visibility != "retrieval_visible":
            return False
        if age_band not in {"0_3", "3_6", "6_12", "12_18"}:
            return True
        if int(metadata.get("age_all", 0)) == 1:
            return True
        return int(metadata.get("age_{0}".format(age_band), 0)) == 1

    if session_type == "parent":
        return visibility in {"parent_only", "retrieval_visible"}

    if session_type == "system":
        return visibility in {"system_only", "parent_only", "retrieval_visible"}

    return False
