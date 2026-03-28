import unittest
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from rebuild_kb import (
    extract_title_from_text,
    guess_recovered_title,
    parse_recovered_filename,
    stable_doc_id_from_storage_key,
)


class RebuildKbHelpersTestCase(unittest.TestCase):
    def test_parse_recovered_filename(self):
        parsed = parse_recovered_filename("685c5b6d-d83c-4514-9a66-e1e9d1423863_v12.md")
        self.assertEqual(
            parsed,
            ("685c5b6d-d83c-4514-9a66-e1e9d1423863", 12, ".md"),
        )

    def test_parse_recovered_filename_returns_none_for_normal_name(self):
        self.assertIsNone(parse_recovered_filename("guide-for-parents.md"))

    def test_extract_title_from_text_prefers_heading(self):
        text = "\n# 儿童睡眠指南\n\n正文内容"
        self.assertEqual(extract_title_from_text(text), "儿童睡眠指南")

    def test_guess_recovered_title_uses_text_for_recovered_file(self):
        title = guess_recovered_title(
            "685c5b6d-d83c-4514-9a66-e1e9d1423863_v1.md",
            "# 家长陪伴建议\n\n内容",
        )
        self.assertEqual(title, "家长陪伴建议")

    def test_stable_doc_id_from_storage_key_is_deterministic(self):
        left = stable_doc_id_from_storage_key("uploads/sample.md")
        right = stable_doc_id_from_storage_key("uploads/sample.md")
        self.assertEqual(left, right)


if __name__ == "__main__":
    unittest.main()
