import os
import sys
import unittest


CURRENT_DIR = os.path.dirname(__file__)
SERVICE_DIR = os.path.abspath(os.path.join(CURRENT_DIR, ".."))
if SERVICE_DIR not in sys.path:
    sys.path.insert(0, SERVICE_DIR)

from kb_logic import (  # noqa: E402
    build_vector_filter,
    chunk_matches_session,
    compute_keyword_overlap,
    default_title_from_filename,
    infer_source_org,
    map_query_topics,
    tokenize_query,
)


def make_metadata(**overrides):
    metadata = {
        "enabled": 1,
        "retrieval_enabled": 1,
        "library": "age_content",
        "visibility": "retrieval_visible",
        "age_all": 0,
        "age_0_3": 0,
        "age_3_6": 0,
        "age_6_12": 1,
        "age_12_18": 0,
    }
    metadata.update(overrides)
    return metadata


def contains_key(obj, key):
    if isinstance(obj, dict):
        if key in obj:
            return True
        return any(contains_key(value, key) for value in obj.values())
    if isinstance(obj, list):
        return any(contains_key(item, key) for item in obj)
    return False


class RetrievalFilterTests(unittest.TestCase):
    def test_child_retrieval_cannot_recall_system_only_chunk(self):
        metadata = make_metadata(visibility="system_only", library="rules")
        self.assertFalse(chunk_matches_session(metadata, "child", "6_12"))

    def test_child_retrieval_cannot_recall_parent_only_chunk(self):
        metadata = make_metadata(visibility="parent_only", library="parent")
        self.assertFalse(chunk_matches_session(metadata, "child", "6_12"))

    def test_mixed_document_child_chunk_is_not_blocked_by_document_library(self):
        metadata = make_metadata(library="rules", visibility="retrieval_visible", age_6_12=1)
        self.assertTrue(chunk_matches_session(metadata, "child", "6_12"))

    def test_child_retrieval_filters_by_age_band(self):
        metadata = make_metadata(age_6_12=0, age_12_18=1)
        self.assertFalse(chunk_matches_session(metadata, "child", "6_12"))
        self.assertTrue(chunk_matches_session(metadata, "child", "12_18"))

    def test_child_retrieval_without_age_band_does_not_apply_age_filter(self):
        metadata = make_metadata(age_6_12=0, age_12_18=1)
        self.assertTrue(chunk_matches_session(metadata, "child", None))

    def test_disabled_and_blocked_do_not_participate_in_retrieval(self):
        disabled = make_metadata(enabled=0)
        blocked = make_metadata(visibility="blocked", retrieval_enabled=0)
        self.assertFalse(chunk_matches_session(disabled, "child", "6_12"))
        self.assertFalse(chunk_matches_session(blocked, "child", "6_12"))
        self.assertFalse(chunk_matches_session(blocked, "parent"))

    def test_child_vector_filter_has_no_library_hard_gate(self):
        where_filter = build_vector_filter("child", "6_12")
        self.assertFalse(contains_key(where_filter, "library"))

    def test_parent_vector_filter_has_no_library_hard_gate(self):
        where_filter = build_vector_filter("parent", "6_12")
        self.assertFalse(contains_key(where_filter, "library"))


class UploadTitleTests(unittest.TestCase):
    def test_source_org_inference_covers_known_and_unknown_sources(self):
        self.assertEqual(
            infer_source_org("UNICEF parenting guide", "guide.pdf", "Practical advice for families.")[0],
            "UNICEF",
        )
        self.assertEqual(
            infer_source_org("Healthy kids handbook", "who_health.pdf", "Published by WHO for caregivers.")[0],
            "WHO",
        )
        self.assertEqual(
            infer_source_org("School health update", "notes.pdf", "CDC guidance for schools.")[0],
            "CDC",
        )
        self.assertEqual(
            infer_source_org("Community notes", "local.txt", "Neighborhood volunteer summary.")[0],
            "Unknown",
        )

    def test_title_defaults_to_filename_without_extension(self):
        self.assertEqual(
            default_title_from_filename("folder/WHO-parent-guide.pdf"),
            "WHO-parent-guide",
        )

    def test_query_topic_mapping_detects_sleep_and_online_safety(self):
        self.assertEqual(map_query_topics("sleep tips for kids"), ["sleep"])
        self.assertEqual(map_query_topics("online privacy and strangers"), ["online_safety"])

    def test_tokenize_query_and_keyword_overlap(self):
        tokens = tokenize_query("sleep for 6 year olds")
        self.assertIn("sleep", tokens)
        self.assertGreater(
            compute_keyword_overlap(tokens, ["Healthy sleep routines for school age children"]),
            0.0,
        )


if __name__ == "__main__":
    unittest.main()
