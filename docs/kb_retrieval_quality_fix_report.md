# KB Retrieval Quality Fix Report

## 1. 当前问题的根因

本次问题的根因在于真实后端检索链此前主要是：

1. 直接做向量召回
2. 再做会话身份 / 年龄段 / visibility 过滤
3. 然后把剩余 top-k 直接返回

这会导致两个问题：

- 没有 `relevance threshold`，所以即使 query 与候选段落主题明显不匹配，也可能因为向量空间“勉强最近”而被返回
- 没有 topic 先验和二次相关性校验，短 query 如 `sleep` 容易召回“儿童 AI 指南 / policy”这类语义泛相近但主题错误的内容

## 2. relevance threshold 如何实现

实现位置：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)

本次新增了两层阈值：

- 距离阈值：`DISTANCE_THRESHOLD = 0.85`
- 综合相关性阈值：`RELEVANCE_SCORE_THRESHOLD = 0.52`

综合相关性分数由以下信号加权计算：

- `semantic_score`
- `keyword_overlap`
- `topic_hit`
- `title_hit / heading_hit`
- `document_topic_hit`

若候选结果：

- 超过距离阈值
- 或综合相关性分数低于阈值
- 或既没有 topic/keyword/title/heading 支撑，又不是强语义近邻

则不会进入最终结果，而会被标记为：

- `passed_relevance_threshold = false`
- `reliable = false`

若所有候选都未通过阈值，则返回：

- `message = 当前知识库中没有找到可靠的匹配结果`
- `reliable = false`

## 3. topic mapping 如何实现

实现位置：

- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)
- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)

新增轻量 query topic mapping：

- `sleep -> sleep`
- `emotion / sad / angry -> emotion`
- `school / homework -> school`
- `online / privacy / stranger -> online_safety`

具体函数：

- `map_query_topics(query)`

检索时逻辑为：

1. 先识别 query topic
2. 若识别到 topic，则优先在对应 `topic_*` 子集里做向量召回
3. 若该 topic 子集没有任何候选，则直接返回：
   - `message = 当前知识库缺少该主题内容`
   - `missing_topic_content = true`

这样可以避免像 `sleep` 这种 query 被无关 topic 的政策文本污染。

## 4. 二次相关性校验如何实现

实现位置：

- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)

流程：

1. 先拿到候选向量结果
2. 再从数据库补全真实 chunk/document 字段：
   - `title`
   - `heading_path`
   - `topics`
   - `age_bands`
   - `visibility`
   - `audience`
3. 用以下信号做二次校验：
   - `keyword_overlap`
   - `title keyword match`
   - `heading match`
   - `topic match`
   - `document topic match`
   - `semantic match`
4. 只有同时通过：
   - 会话/年龄段/visibility 真实过滤
   - 距离阈值
   - relevance threshold

的结果才进入最终 `results`

未通过的候选会进入 `filtered_out`，并返回明确原因，例如：

- `distance_above_threshold`
- `topic_mismatch`
- `no_keyword_or_topic_support`
- `below_relevance_threshold`
- `age_band_mismatch`
- `visibility_not_child_safe`

## 5. 检索测试页新增展示字段

实现位置：

- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php)

每条结果现在至少展示：

- 文档标题
- `heading_path`
- `topics`
- `age_bands`
- `visibility`
- `audience`
- `distance`
- `score`
- 命中原因
- 匹配信号
- 是否通过 `relevance threshold`

页面顶部还新增展示：

- `reliable`
- `query_topics`
- `topic_filter_applied`
- 后端返回的 `message`

若没有可靠结果，页面会明确显示：

- 当前知识库中没有找到可靠的匹配结果

并提示可能原因：

1. query 与当前知识库主题不匹配
2. 当前主题文档尚未导入
3. 年龄段 / visibility 过滤后无可用结果

## 6. 如何验证“sleep”这类 query 不再返回伪相关结果

### 建议手动验证 query

强相关 query：

- `privacy advice for kids online`

预期：

- 若库中已有网络安全内容，应返回 `online_safety` 主题结果
- `reliable = true`
- 结果中的 `topics` 应包含 `online_safety` 或相关 document topic

弱相关 query：

- `healthy routines`

预期：

- 可能返回少量结果，也可能返回无可靠结果
- 若返回，必须有明确 `match_signals`
- 不应无脑返回一批弱相关政策段落

明显无关 query：

- `sleep`

预期：

- 若知识库缺少 `sleep` 主题内容，应返回：
  - `message = 当前知识库缺少该主题内容`
  - 或 `message = 当前知识库中没有找到可靠的匹配结果`
- 不应再返回明显无关的 AI policy / children and AI guidance 段落
- 若 `filtered_out` 中出现这些政策段落，应带有类似：
  - `topic_mismatch`
  - `below_relevance_threshold`
  - `no_keyword_or_topic_support`

## 7. 本次实际修改的关键文件

- [services/chroma/kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/kb_logic.py)
- [services/chroma/main.py](/home/wkd/AIChatforChildren/services/chroma/main.py)
- [services/chroma/tests/test_kb_logic.py](/home/wkd/AIChatforChildren/services/chroma/tests/test_kb_logic.py)
- [pages/admin/knowledge.php](/home/wkd/AIChatforChildren/pages/admin/knowledge.php)

## 8. 验证结果

本次已执行：

- `python3 -m py_compile services/chroma/main.py services/chroma/kb_logic.py services/chroma/kb_repository.py`
- `"/home/wkd/miniconda3/envs/py39/bin/python" -m unittest discover -s services/chroma/tests -p 'test_*.py' -v`
- `php -l pages/admin/knowledge.php`
- 前端脚本 `node --check`

结果：

- Python 语法通过
- PHP 语法通过
- 前端脚本语法通过
- `services/chroma/tests` 共 11 个测试通过
