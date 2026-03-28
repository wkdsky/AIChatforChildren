# KB Frontend Upload Auto Flow Report

## 1. 本次新增/修改文件清单

前端主改造：

- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php)

为支撑前端真实对接而补齐的接口层：

- [app/controllers/KnowledgeController.php](/home/wkd/AIChatforChildren/app/controllers/KnowledgeController.php)
- [core/AppRouter.php](/home/wkd/AIChatforChildren/core/AppRouter.php)

为支撑前端上传自动分析、状态页、chunk 审查和检索测试而补齐/扩展的知识库后端：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)
- [services/chroma/kb_repository.py](/home/wkd/AIChatforChildren/services/chroma/kb_repository.py)

本次报告：

- [docs/kb_frontend_upload_auto_flow_report.md](/home/wkd/AIChatforChildren/docs/kb_frontend_upload_auto_flow_report.md)

## 2. 上传页现在有哪些交互元素

上传页首屏现在只有两个可交互输入：

1. `file`
2. `title`

以及一个提交按钮：

- `开始上传`

没有再暴露任何人工 metadata 输入框。以下字段不再出现在上传首屏：

- `source_org`
- `language`
- `doc_type`
- `library`
- `audience`
- `age_band / age_bands`
- `safety_visibility`
- `topics`
- `tags`
- `risk_level`
- `summary`
- `priority`
- `format`
- `source_url`

## 3. title 默认取文件名的实现位置

前端默认标题生成实现位于：

- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php)

关键函数：

- `defaultTitleFromFilename(filename)`

关键交互：

- 选择文件后调用 `defaultTitleFromFilename`
- 去掉扩展名
- 若文件名异常则回退为 `Untitled Document`
- 若管理员尚未主动改过 title，则自动填充到 title 输入框

后端默认兜底仍保留上一阶段逻辑：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)

## 4. 自动分析流程页如何工作

上传接口现在不是前端本地模拟步骤，而是：

1. `POST /api/knowledge/upload`
2. Python 服务先落原文件与 pending 文档记录
3. 后台异步执行自动分析
4. 前端跳转到 `#process/{doc_id}`
5. 轮询 `GET /api/knowledge/files/{doc_id}/status`

状态页展示 7 个阶段：

1. 原文件已保存
2. 文档解析中
3. 文本切片中
4. 自动分类中
5. 自动生成年龄段/受众/可见性/主题中
6. 构建 embedding / 建立索引中
7. 分析完成

刷新页面后仍可通过 hash 中的 `doc_id` 恢复状态页。

## 5. 分析结果确认页如何工作

确认页入口：

- 上传完成后自动跳转
- 文档列表中点击“分析结果”

页面默认简洁模式，展示：

- `title`
- `source_org`
- `library`
- `audience`
- `age_bands`
- `safety_visibility`
- `topics`
- `summary`
- `review_status`

交互：

- `title` 可编辑并单独保存
- `一键确认` 会把 `review_status` 更新为 `auto_accepted`
- `重新分析` 触发真实后端重跑
- 可切到高级模式做受限修正

## 6. 文档详情页与 chunk 审查页如何工作

### 文档详情页

入口：

- 文档列表“详情”

展示内容：

- 原始文件信息
- 文档级自动字段
- `parser_status / chunk_status / embedding_status / indexing_status`
- `version / last_indexed_at`
- chunk 统计

支持动作：

- 启用/禁用：通过高级模式中的 `enabled`
- 重新分析
- 重新解析
- 重新切片
- 重建索引
- 进入 chunk 审查页

### chunk 审查页

入口：

- 文档列表“Chunk 审查”
- 文档详情中的“Chunk 审查”

展示字段：

- `chunk_index`
- `heading_path`
- 内容预览
- `age_bands`
- `audience`
- `visibility`
- `topics`
- `risk_level`
- `retrieval_enabled`
- `confidence`

支持：

- 按 `visibility / audience / age_bands / retrieval_enabled` 过滤
- 按 `confidence` 升序排序
- 查看单个 chunk 详情
- 批量修正：
  - `age_bands`
  - `audience`
  - `visibility`
  - `topics`
  - `retrieval_enabled`

## 7. 简洁模式 / 高级模式差异

### 简洁模式

面向普通管理员，重点是：

- 看系统建议
- 改 `title`
- 一键确认
- 必要时进入 chunk 审查

默认只突出关键字段，不展示过多底层索引细节。

### 高级模式

面向运维/内容审核，额外显示并允许修正：

- `source_org`
- `library`
- `audience`
- `age_bands`
- `safety_visibility`
- `topics`
- `summary`
- `risk_level`
- `enabled`
- `review_status`

并提供：

- 重新解析
- 重新切片
- 重建索引
- chunk 批量修正

## 8. 前端如何展示与修正系统自动生成字段

严格对齐上一阶段字段体系：

- 文档级和 chunk 级 `age_bands` 都按数组展示
- 文档级 `safety_visibility = mixed` 被明确提示为“分析结果”，不是儿童可检索结论
- 检索测试不在前端模拟 `visibility + retrieval_enabled + age_bands` 规则，而是直接调用真实后端

修正策略：

- 上传页：只允许改 `title`
- 确认页/详情页：默认只读展示系统字段
- 高级模式下：受限开放文档级修正
- chunk 审查页：受限开放 chunk 级批量修正

## 9. 前端如何与上一阶段后端接口对接

复用并扩展了真实接口。

上传与状态：

- `POST /api/knowledge/upload`
- `GET /api/knowledge/files/{doc_id}/status`

文档列表与详情：

- `GET /api/knowledge/files`
- `GET /api/knowledge/files/{doc_id}`
- `POST /api/knowledge/files/{doc_id}/update`

chunk 审查：

- `GET /api/knowledge/files/{doc_id}/chunks`
- `GET /api/knowledge/files/{doc_id}/chunks/{chunk_id}`
- `POST /api/knowledge/files/{doc_id}/chunks/bulk-update`

运维动作：

- `POST /api/knowledge/files/{doc_id}/actions/reanalyze`
- `POST /api/knowledge/files/{doc_id}/actions/reparse`
- `POST /api/knowledge/files/{doc_id}/actions/rechunk`
- `POST /api/knowledge/files/{doc_id}/actions/reindex`

检索测试：

- `GET /api/knowledge/search`
  - 支持 `session_type`
  - child 支持 `age_band`
  - 支持 `include_filtered=1`

## 10. 手动测试步骤

1. 打开管理员知识库页。
2. 进入“上传”页，确认只有 `file` 和 `title` 两个输入。
3. 选择文件，确认 `title` 自动变成文件名去扩展名。
4. 不填其他 metadata，直接上传。
5. 确认页面跳到“系统分析流程”，并能轮询状态。
6. 分析完成后确认自动进入“分析结果确认页”。
7. 检查文档级 `age_bands` 是否以多值形式展示。
8. 打开高级模式，检查 `age_bands` 是否为多选修正。
9. 进入 chunk 审查页，确认：
   - `age_bands` 以多值展示
   - 默认可按 `confidence` 升序查看低置信度 chunk
   - 可按 `visibility / audience / age_bands / retrieval_enabled` 过滤
   - 可批量修正 chunk 字段
10. 打开检索测试页，分别测试：
   - `child + 6_12`
   - `parent`
   - `system`
11. 检查检索结果区和“被过滤掉的 chunk”区都来自真实后端返回。
12. 修改 `title` 后保存，确认不影响其余自动字段结构。

## 11. 已知限制

- 异步分析使用的是 FastAPI `BackgroundTasks`，属于同进程后台任务，不是独立队列系统。
- `reparse` 与 `rechunk` 当前都会走重新分析主流程，适合当前后台管理场景，但还可以继续细分为更严格的分步重建。
- 文档列表筛选目前主要在前端完成，接口仍返回完整列表；如果知识库规模继续增大，建议后续补服务端分页与服务端过滤。
- chunk 批量修正后，当前不会自动回推重算文档级 `safety_visibility / review_status` 聚合结论；如果需要更严格的一致性，可在后端增加聚合回写步骤。
- 这次验证已完成 Python / PHP / 前端脚本语法检查，以及现有 `kb_logic` 单测；没有在浏览器里做实际点击录屏式验收。
