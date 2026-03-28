# KB ChromaDB Refactor Report

## 1. 现状简述

本次改造前，知识库后端主要集中在 [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py) 的单文件 FastAPI 服务中：

- 上传时只保存原文件，并直接切 chunk 写入单个 Chroma collection。
- 没有独立的文档表 / chunk 表，文档级和 chunk 级业务元数据基本不存在。
- Chroma metadata 只有 `file_id / filename / original_filename / file_type / file_size / upload_time / chunk_index` 等轻量字段。
- 聊天检索入口 [app/controllers/ChatController.php](/home/wkd/AIChatforChildren/app/controllers/ChatController.php) 直接调用 `/api/context`，没有会话类型、年龄段、visibility、library 隔离规则。
- 管理端上传页只支持文件上传，重命名语义接近“改文件名”，不是“改 title”。

本次改造后：

- 新增了 MySQL 主数据层：`kb_documents` + `kb_chunks`。
- 上传链路改成“原文件保存 + 自动分类 + chunk 分类 + Chroma 扁平 metadata upsert”。
- 检索严格基于 chunk metadata 过滤，并区分 `child / parent / system` 会话。
- `title` 成为唯一人工可编辑字段，默认从文件名去扩展名自动生成。

## 1.1 修正说明

### 修正前的问题

- child / parent 检索规则里曾把 `document.library` 当成最终硬门槛。
- 这会让“文档级 mixed / rules / parent 标签”错误影响 chunk 级放行结果，不符合“最终以 chunk 字段为准”的目标。
- 测试部分此前只有语法检查和手工 Python 断言，不属于稳定可重复执行的自动化测试。

### 修正后的检索逻辑

- child 最终放行只看：
  - `enabled = 1`
  - `retrieval_enabled = 1`
  - `visibility = retrieval_visible`
  - `age_all = 1` 或命中请求年龄段
- parent 最终放行只看：
  - `enabled = 1`
  - `retrieval_enabled = 1`
  - `visibility in (parent_only, retrieval_visible)`
- system 最终放行只看：
  - `enabled = 1`
  - `retrieval_enabled = 1`
  - `visibility in (system_only, parent_only, retrieval_visible)`
- `document.library` 现在只保留为管理字段、分类信号和后续排序/诊断信号，不再是 child / parent 的最终硬门槛。

### 自动化测试如何运行

本次已补成可直接执行的标准库 `unittest`：

```bash
"/home/wkd/miniconda3/envs/py39/bin/python" -m unittest discover -s services/chroma/tests -p 'test_*.py' -v
```

本次已实际执行，结果为 9 个测试全部通过。

### 第一阶段与第二阶段边界

- 第一阶段只做后端、数据模型、自动元数据生成、向量 metadata、检索过滤、测试与报告。
- 不进入新的前端上传流设计，不做新的上传确认界面或完整表单交互。
- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php) 本次修改仅为接口兼容：
  - 列表优先显示 `title`
  - 重命名按钮语义改为“编辑 title”
  - 没有引入新的前端上传流程

## 2. 本次新增/修改的文件清单

新增：

- [database/migrations/CreateKnowledgeBaseTables.php](/home/wkd/AIChatforChildren/database/migrations/CreateKnowledgeBaseTables.php)
- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)
- [services/chroma/kb_repository.py](/home/wkd/AIChatforChildren/services/chroma/kb_repository.py)
- [services/chroma/tests/test_kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/tests/test_kb_logic.py)
- [docs/kb_chromadb_refactor_report.md](/home/wkd/AIChatforChildren/docs/kb_chromadb_refactor_report.md)

修改：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)
- [services/chroma/config.py](/home/wkd/AIChatforChildren/services/chroma/config.py)
- [services/chroma/requirements.txt](/home/wkd/AIChatforChildren/services/chroma/requirements.txt)
- [app/controllers/KnowledgeController.php](/home/wkd/AIChatforChildren/app/controllers/KnowledgeController.php)
- [app/controllers/ChatController.php](/home/wkd/AIChatforChildren/app/controllers/ChatController.php)
- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php)

## 3. 数据模型与 migration 变更说明

### `kb_documents`

核心字段：

- `doc_id`
- `document_key`
- `title`
- `original_filename`
- `file_ext`
- `mime_type`
- `format`
- `storage_key`
- `file_size`
- `content_hash`
- `version`
- `language`
- `source_org` / `source_org_confidence`
- `library` / `library_confidence`
- `audience` / `audience_confidence`
- `age_bands` / `age_bands_confidence`
- `safety_visibility`
- `topics`
- `tags`
- `summary`
- `risk_level`
- `enabled`
- `review_status`
- `parser_status`
- `chunk_status`
- `embedding_status`
- `indexing_status`
- `created_at`
- `updated_at`
- `last_indexed_at`

设计说明：

- `age_bands / topics / tags` 使用 JSON 保存规范数组。
- `document_key` 用于版本线索归并，基于 title 规范化。
- `content_hash` 用于重复导入判断。
- `enabled` 与 `review_status` 分开，便于“可存在但不可用于正常检索”的状态表达。

### `kb_chunks`

核心字段：

- `chunk_id`
- `doc_id`
- `chunk_index`
- `heading_path`
- `content`
- `char_count`
- `token_count`
- `age_bands`
- `audience`
- `visibility`
- `topics`
- `risk_level`
- `retrieval_enabled`
- `confidence`
- `chunk_summary`
- `embedding_id`
- `vector_id`
- `created_at`
- `updated_at`

设计说明：

- `chunk` 级别保留规范字段，检索时真正使用 chunk 分类结果，而不是只看 document。
- `heading_path` 当前用 JSON 字段保存，便于后续扩展成数组路径。
- `retrieval_enabled` 和 `visibility` 分离，前者表示是否允许进入检索集合，后者表示允许被谁检索。

### migration 执行结果

我已实际执行：

```bash
php database/migrate.php
```

输出显示 `kb_documents` 与 `kb_chunks` 已成功创建。

## 4. 文档级自动生成字段清单与规则

实际实现位置：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)
- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)

规则摘要：

- `doc_id`：UUID 自动生成。
- `title`：默认由 `default_title_from_filename()` 从上传文件名去扩展名得到；上传时允许透传覆盖。
- `original_filename`：来自上传文件。
- `file_ext / mime_type / format`：由文件扩展名和 `mimetypes` 自动识别。
- `storage_key`：保存为 `storage/knowledge/uploads/` 下相对路径。
- `content_hash`：原始二进制 SHA-256。
- `version`：基于 `document_key` 取现有最大版本号 +1；若同一 `document_key + content_hash` 已存在，则判定为重复导入并直接返回已有文档。
- `language`：基于中英文字符占比检测 `zh / en / mixed / other`。
- `source_org`：按 title / filename 优先，再按正文前部匹配；支持 `UNICEF / WHO / CDC / AAP / NSPCC / ITU / Common Sense / Unknown`。
- `library`：规则库 / 家长库 / 儿童内容库 / 混合库，通过关键词打分。
- `audience`：`system / parent / child / teacher / mixed`，通过关键词打分。
- `age_bands`：多值数组，优先显式年龄表达，再用语义词回退；对 rules / parent 无明确年龄时回退到 `[all]`。
- `safety_visibility`：按 `library + audience + risk` 生成。
- `topics`：受控标签集合自动抽取。
- `tags`：少量扩展标签自动补充。
- `summary`：规则摘要，当前取前 1-2 句。
- `risk_level`：按危机 / 在线风险 / 普通教育内容规则分 `high / medium / low`。
- `enabled`：解析成功默认 true；解析失败落失败记录时为 false。
- `review_status`：`auto_accepted / needs_review / blocked`，结合解析状态、置信度、混合程度和高风险判断。
- `parser_status / chunk_status / embedding_status / indexing_status`：由 pipeline 流程维护。

## 5. chunk 级自动生成字段清单与规则

实际实现位置：

- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)

规则摘要：

- `chunk_id`：`{doc_id}_chunk_{chunk_index}`
- `heading_path`：按 Markdown / 编号标题简单切段；非结构化文本回退为 `Root`
- `content / char_count / token_count`：切 chunk 时同步生成
- `age_bands`：可比文档更细；显式年龄优先，否则用 chunk 语义规则
- `audience`：chunk 强制收敛到单值 `system / parent / child / teacher`
- `visibility`：
  - `system_only`：system 受众
  - `parent_only`：parent / teacher 受众
  - `retrieval_visible`：child 且风险/置信度允许
  - `blocked`：高风险低置信度或不安全 chunk
- `topics`：chunk 级重新抽取，不直接继承文档
- `risk_level`：chunk 级重新判定
- `retrieval_enabled`：blocked 或 child 高风险 chunk 关闭；system / parent chunk 仍可用于内部或成人检索
- `confidence`：当前基于 audience + age_bands 置信度均值
- `chunk_summary`：可选短摘要
- `embedding_id / vector_id`：和 chunk_id 对齐

## 6. ChromaDB metadata 扁平化设计说明

实际实现位置：

- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)

扁平字段已实现：

- `doc_id`
- `chunk_id`
- `enabled`
- `retrieval_enabled`
- `library`
- `audience`
- `visibility`
- `risk_level`
- `review_status`
- `language`
- `age_all`
- `age_0_3`
- `age_3_6`
- `age_6_12`
- `age_12_18`
- `topic_milestones`
- `topic_emotion`
- `topic_play`
- `topic_parenting`
- `topic_online_safety`
- `topic_media_use`
- `topic_school`
- `topic_sleep`
- `topic_health`
- `topic_social`
- `topic_learning`
- `topic_crisis`

补充字段：

- `title`
- `original_filename`
- `source_org`
- `chunk_index`
- `confidence`

设计原则：

- 主数据库保留规范数组结构。
- Chroma 只保存稳定可过滤的扁平字段。
- 检索时先尝试把过滤条件下推到 Chroma `where`，再做一次服务端 post-filter，确保规则严格生效。

## 7. 检索过滤逻辑说明

实际实现位置：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)
- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)

### child 会话

- 仅允许：
  - `enabled = 1`
  - `retrieval_enabled = 1`
  - `visibility = retrieval_visible`
  - `age_all = 1` 或命中当前年龄段
- 明确拒绝：
  - `system_only`
  - `parent_only`
  - `blocked`

### parent 会话

- 允许：
  - `parent_only`
  - `retrieval_visible`
- 拒绝：
  - `system_only`
  - `blocked`

### system 会话

- 允许：
  - `system_only`
  - `parent_only`
  - `retrieval_visible`
- 仍拒绝：
  - disabled
  - `retrieval_enabled = 0`
  - blocked chunk

### mixed 文档

- 不按文档整体放行。
- 真正的访问控制由 chunk 的 `visibility + retrieval_enabled + age_bands` 决定。
- `document.library` 不再是最终硬门槛。

## 8. 自动分类器设计说明

### 当前实现

已实现规则分类器，核心在 [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)：

- 机构识别：规则词典 + 标题 / 文件名优先 + 正文前部补充
- library 分类：关键词打分
- audience 分类：关键词打分
- age_bands 分类：显式年龄模式优先，语义关键词回退
- topics：受控标签自动抽取
- risk_level：高风险 / 中风险关键词优先
- review_status：基于解析状态、风险和置信度

### 可扩展结构

虽然当前没有接入 LLM 分类器，但结构已经拆开：

- pipeline 步骤在 [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py) 的 `KnowledgeIngestionPipeline`
- 分类核心逻辑在 [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)

后续可以把 `classifyDocument()` 和 `classifyChunks()` 内部实现替换为：

- 规则优先 + 模型校准
- 纯模型分类
- 规则 + review queue 混合模式

而不必再改上传 / 入库 / 检索主流程。

## 9. 手动测试步骤

### 已实际执行

1. 运行 migration

```bash
php database/migrate.php
```

2. Python 语法检查

```bash
python3 -m py_compile services/chroma/main.py services/chroma/kb_logic.py services/chroma/kb_repository.py
```

3. PHP 语法检查

```bash
php -l app/controllers/KnowledgeController.php
php -l app/controllers/ChatController.php
php -l database/migrations/CreateKnowledgeBaseTables.php
```

4. 规则逻辑手动断言

5. 自动化测试

```bash
"/home/wkd/miniconda3/envs/py39/bin/python" -m unittest discover -s services/chroma/tests -p 'test_*.py' -v
```

已实际通过的自动化测试包括：

- child 不能召回 `system_only`
- child 不能召回 `parent_only`
- mixed 文档中的 child chunk 不会被 document.library 卡掉
- child 按 chunk `age_bands` 过滤正确
- disabled / blocked 不参与检索
- `UNICEF / WHO / CDC / Unknown` source_org 推断
- title 默认来自文件名去扩展名

### 建议你本地继续手工验证

1. 启动 Chroma 服务。
2. 上传 1 份明显 parent 文档、1 份 rules 文档、1 份 child 活动文档。
3. 观察 `/api/knowledge/files` 返回是否包含：
   - `title`
   - `library`
   - `audience`
   - `age_bands`
   - `review_status`
4. 用 child 会话调用 `/api/context?session_type=child&age_band=6_12`
   - 确认只返回 `retrieval_visible`
5. 用 parent 会话调用 `/api/search?session_type=parent`
   - 确认可以看到 `parent_only`
6. 用 system 会话调用 `/api/search?session_type=system`
   - 确认可以命中 rules chunk
7. 上传同一 title 的同内容文件
   - 确认返回 duplicate 提示
8. 上传同一 title 的不同内容文件
   - 确认 `version` 递增

## 10. 已知限制 / 下一阶段前端需要对接的接口说明

### 已知限制

- 当前 child 会话如果没有显式传 `age_band`，后端默认使用 `6_12`。
  - 原因：现有用户模型没有儿童年龄字段。
- `.doc` 仍未做真正解析，当前会走 `other` / unsupported parser 分支。
- 规则分类器是可工作的第一版，但不是模型级语义分类，边界内容仍可能落 `needs_review`。
- 当前自动化测试采用 `unittest`，没有继续依赖 `pytest`。
- 失败文档虽然会落失败记录，但当前管理页还没有单独展示 `parser_status / review_status / indexing_status` 的完整 UI。

### 下一阶段前端需要对接

上传接口：

- `POST /api/knowledge/upload`
- 当前只要求：
  - `file` 必填
  - `title` 可选

列表接口：

- `GET /api/knowledge/files`
- 现在返回 `title`，前端应优先展示 `title`，不要再把 `original_filename` 当成人工可编辑名称

重命名接口：

- `POST /api/knowledge/rename`
- `new_name` 现在语义上是“新 title”

聊天 / 检索接口：

- `/api/knowledge/context`
- `/api/knowledge/search`

建议下一阶段前端补传：

- `session_type`
- child 当前年龄段 `age_band`

说明：

- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php) 在第一阶段只做了最小接口兼容，没有进入第二阶段上传流改造。

## 11. mixed 文档处理示例

假设上传一份标题为 `Family Digital Safety Pack` 的混合文档：

- 前半段是家长网络安全建议
- 后半段是给 12-18 岁孩子的自护活动

### 文档级 metadata 示例

```json
{
  "doc_id": "2e3f0d5a-4f3e-4cf0-b089-123456789abc",
  "title": "Family Digital Safety Pack",
  "original_filename": "family_digital_safety_pack.pdf",
  "format": "pdf",
  "content_hash": "sha256:...",
  "version": 1,
  "language": "en",
  "source_org": "Unknown",
  "library": "mixed",
  "audience": "mixed",
  "age_bands": ["12_18", "all"],
  "safety_visibility": "mixed",
  "topics": ["online_safety", "parenting", "social"],
  "risk_level": "medium",
  "enabled": true,
  "review_status": "needs_review",
  "parser_status": "completed",
  "chunk_status": "completed",
  "embedding_status": "completed",
  "indexing_status": "completed"
}
```

### chunk 级 metadata 示例

家长 chunk：

```json
{
  "chunk_id": "2e3f0d5a-4f3e-4cf0-b089-123456789abc_chunk_0",
  "doc_id": "2e3f0d5a-4f3e-4cf0-b089-123456789abc",
  "chunk_index": 0,
  "heading_path": "Parents Guide",
  "age_bands": ["all"],
  "audience": "parent",
  "visibility": "parent_only",
  "topics": ["online_safety", "parenting"],
  "risk_level": "medium",
  "retrieval_enabled": true,
  "confidence": 0.81
}
```

孩子 chunk：

```json
{
  "chunk_id": "2e3f0d5a-4f3e-4cf0-b089-123456789abc_chunk_3",
  "doc_id": "2e3f0d5a-4f3e-4cf0-b089-123456789abc",
  "chunk_index": 3,
  "heading_path": "Teen Activities",
  "age_bands": ["12_18"],
  "audience": "child",
  "visibility": "retrieval_visible",
  "topics": ["online_safety", "social"],
  "risk_level": "medium",
  "retrieval_enabled": true,
  "confidence": 0.76
}
```

这意味着：

- child `6_12` 检索不会召回该 teen chunk
- child `12_18` 可以召回该 teen chunk
- parent 检索可以看到 parent chunk
- system 检索可以同时看到不同 visibility 的可检索 chunk
