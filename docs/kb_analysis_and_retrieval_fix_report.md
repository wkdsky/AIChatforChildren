# 管理员知识库页面分析与检索修复报告

## 1. 根因分析：为什么上传后没有自动分析

根因分两层：

1. 当前管理员页和 PHP 桥接层已经按“新版知识库服务接口”工作，但 `127.0.0.1:4001` 上实际运行的仍是旧版 Python 服务。
   - 旧服务的 `/api/upload` 仍是旧同步上传实现。
   - 旧服务返回的文件列表缺少 `source_org / age_bands / topics / parser_status / chunk_status / embedding_status / indexing_status / error_message` 等字段。
   - 因此前端列表只能显示文件名、大小、chunk 数量，而系统字段退回为默认占位值，例如“无年龄段 / Unknown / 无主题 / 未索引”。

2. 仓库内新版服务虽然已经具备自动分析链路，但原实现仍有两个可见性缺口：
   - 失败原因没有独立 `error_message` 字段，失败时只会覆盖 `summary`。
   - 异常时会把多个阶段一并标成失败，管理员无法判断卡在解析、切片还是索引。

本次修复后，新版服务的上传链路为：

1. `POST /api/upload`
2. `queue_upload()` 保存原文件，创建 `kb_documents` 的 pending 记录
3. 后台任务触发 `process_pending_upload(doc_id)`
4. 依次执行：
   - `parseDocument`
   - `classifyDocument`
   - `chunkDocument`
   - `classifyChunks`
   - `buildEmbeddings`
   - `upsertToVectorStore`
   - `updateIndexStatuses`
5. 前端轮询 `GET /api/knowledge/files/{doc_id}/status`
6. 最终状态进入 `completed`，或在页面展示明确 `error_message`

## 2. 根因分析：为什么会出现 Method Not Allowed

直接原因是接口版本不一致。

管理员页当前会调用这些新版能力：

- `GET /api/files/{file_id}`
- `GET /api/files/{file_id}/status`
- `GET /api/files/{file_id}/chunks`
- `GET /api/files/{file_id}/chunks/{chunk_id}`
- `PUT /api/files/{file_id}/chunks/bulk`
- `POST /api/files/{file_id}/actions/{action}`

但当前 4001 端口旧服务实际只暴露：

- `POST /api/upload`
- `GET /api/files`
- `DELETE /api/files/{file_id}`
- `PUT /api/files/{file_id}/rename`
- `GET /api/search`
- `GET /api/context`

因此：

- 打开“分析结果 / 详情”时，请求会落到旧服务的 `/api/files/{file_id}`，该路由只允许 `DELETE`，于是返回 `405 Method Not Allowed`
- `status / chunks / actions` 等新版路径在旧服务上根本不存在，会返回 `404` 或无法代理

本次修复额外补了兼容检查：

- PHP `health()` 现在会读取 `/openapi.json`
- 若缺少管理员页所需接口，会返回 `api_compatible = false`
- 管理员页会直接显示“当前服务仍是旧接口版本”，并提前阻止会报 405 的操作

## 3. 修复了哪些接口、哪些页面按钮

### 后端服务修复

- `services/chroma/main.py`
  - 上传后后台自动分析链路保留并补强
  - 新增 `error_message` 返回
  - 状态失败时按阶段保留已完成进度
  - 检索接口新增 `indexed_document_count`
  - 当没有任何已索引文档时，返回明确 `no_result_reason = no_indexed_documents`

- `services/chroma/kb_repository.py`
  - 启动时自动补齐 `kb_documents.error_message` 列
  - `insert_document()` 支持写入 `error_message`
  - 新增 `count_indexed_documents()`

- `database/migrations/CreateKnowledgeBaseTables.php`
  - 新建表结构时包含 `error_message`

### PHP 桥接层修复

- `app/controllers/KnowledgeController.php`
  - `health()` 新增服务 API 兼容性检查
  - 返回 `api_compatible / missing_endpoints / service_version`

### 管理员页面修复

- `pages/admin/knowledge.php`
  - 上传页在服务仍是旧接口时禁用上传并给出明确提示
  - “分析结果 / 详情 / Chunk 审查 / 上传后状态查询 / 运行真实检索”在服务不兼容时提前阻止
  - 文档列表、流程页、详情页补 `error_message`
  - 列表/详情/流程页强化 `parser_status / chunk_status / embedding_status / indexing_status / review_status`
  - 检索测试页补 `indexed_document_count` 和“无已索引文档”提示

## 4. 上传后自动分析链路现在如何工作

### 前端

- 上传使用：`POST /api/knowledge/upload`
- 上传成功后进入流程页
- 流程页轮询：`GET /api/knowledge/files/{doc_id}/status`

### PHP 桥接层

- `POST /api/knowledge/upload` -> `POST /api/upload`
- `GET /api/knowledge/files/{doc_id}/status` -> `GET /api/files/{file_id}/status`

### Python 服务

- `upload_file()` 返回 `processing=true`
- 同时通过 `BackgroundTasks` 调用 `process_pending_upload(doc_id)`
- 状态推进规则：
  - 解析完成：`parser_status=completed`
  - 切片完成：`chunk_status=completed`
  - 向量与索引完成：`embedding_status=completed`、`indexing_status=completed`
  - 任一阶段失败：保留已完成阶段，未完成阶段标 `failed`，并写入 `error_message`

## 5. 哪些状态字段现在可以在页面看到

以下字段现在可见：

- `parser_status`
- `chunk_status`
- `embedding_status`
- `indexing_status`
- `review_status`
- `error_message`

展示位置：

- 文档列表
- 上传后流程页
- 文档详情页
- 分析结果确认页

## 6. 检索测试页现在如何工作

检索测试页提交参数：

- `query`
- `session_type`
- `age_band`，仅 child 会带上
- `include_filtered=1`

接口链路：

- 前端：`GET /api/knowledge/search?...`
- PHP：`GET /api/search?...`

返回后页面会展示：

- 命中文档 `title`
- chunk 片段正文
- `visibility`
- `age_bands`
- `audience`
- `review_status`
- `distance / score`
- 过滤原因或命中原因

若当前没有已索引文档：

- 后端返回 `indexed_document_count = 0`
- `message = 当前没有任何已索引文档，无法执行真实检索`
- `no_result_reason = no_indexed_documents`
- 页面会显示明确提示，不再静默失败

## 7. 页面按钮与实际接口 / method 对照

### 文档相关

- 上传
  - 前端：`POST /api/knowledge/upload`
  - PHP -> Python：`POST /api/upload`

- 上传后状态查询
  - 前端：`GET /api/knowledge/files/{doc_id}/status`
  - PHP -> Python：`GET /api/files/{file_id}/status`

- 分析结果
  - 前端：`GET /api/knowledge/files/{doc_id}`
  - PHP -> Python：`GET /api/files/{file_id}`

- 详情
  - 前端：`GET /api/knowledge/files/{doc_id}`
  - PHP -> Python：`GET /api/files/{file_id}`

- 保存文档修正
  - 前端：`POST /api/knowledge/files/{doc_id}/update`
  - PHP -> Python：`PUT /api/files/{file_id}`

- 重新分析 / 重新解析 / 重新切片 / 重建索引
  - 前端：`POST /api/knowledge/files/{doc_id}/actions/{action}`
  - PHP -> Python：`POST /api/files/{file_id}/actions/{action}`

### Chunk 相关

- Chunk 审查列表
  - 前端：`GET /api/knowledge/files/{doc_id}/chunks`
  - PHP -> Python：`GET /api/files/{file_id}/chunks`

- Chunk 详情弹窗
  - 前端：`GET /api/knowledge/files/{doc_id}/chunks/{chunk_id}`
  - PHP -> Python：`GET /api/files/{file_id}/chunks/{chunk_id}`

- 批量修正 chunk
  - 前端：`POST /api/knowledge/files/{doc_id}/chunks/bulk-update`
  - PHP -> Python：`PUT /api/files/{file_id}/chunks/bulk`

### 检索测试

- 运行真实检索
  - 前端：`GET /api/knowledge/search?...`
  - PHP -> Python：`GET /api/search?...`

## 8. 如何手动验证

1. 启动或重启 `services/chroma` 当前仓库代码
2. 打开管理员知识库页
3. 确认顶部状态不是“旧接口版本”
4. 上传一个 TXT / PDF / DOCX 文档
5. 上传成功后进入流程页，观察：
   - `parser_status`
   - `chunk_status`
   - `embedding_status`
   - `indexing_status`
6. 等待状态变为 `completed`
7. 返回文档列表，确认不再只是：
   - 无年龄段
   - Source org: Unknown
   - 无主题
   - Indexed: 未索引
8. 点击“分析结果”
9. 点击“详情”
10. 点击“Chunk 审查”
11. 打开“检索测试页”，输入：
   - `query`
   - `session identity`
   - `age_band`
12. 点击“运行真实检索”
13. 确认结果卡片至少展示：
   - 文档标题
   - chunk 片段
   - visibility
   - age_bands
   - audience

## 9. 本次本地验证结果

已完成：

- `python3 -m py_compile services/chroma/main.py services/chroma/kb_repository.py services/chroma/kb_logic.py`
- `php -l app/controllers/KnowledgeController.php`
- `php -l pages/admin/knowledge.php`
- `python -m unittest discover -s services/chroma/tests -p 'test_*.py' -v`

还做了临时服务验证：

- 以当前仓库代码启动临时服务在 `127.0.0.1:4010`
- 验证 `POST /api/upload` 返回 `processing=true`
- 3 秒后 `GET /api/files/{doc_id}/status` 变为：
  - `parser_status=completed`
  - `chunk_status=completed`
  - `embedding_status=completed`
  - `indexing_status=completed`
- `GET /api/files/{doc_id}` 与 chunk 相关接口可以正常返回新版字段

## 10. 未完成项 / 需要执行的运维动作

代码侧修复已完成，但当前真实页面若仍连接 `127.0.0.1:4001` 上的旧服务，问题仍会继续出现。

必须执行：

1. 停掉当前 4001 端口旧版知识库服务
2. 用仓库里的 `services/chroma/main.py` 重新启动服务
3. 再按上面的手动验证步骤验收

也就是说：

- 代码已经修到新版接口一致
- 但若不重启/替换当前运行中的旧 Python 服务，`Method Not Allowed` 仍会来自旧进程，而不是来自当前仓库代码
