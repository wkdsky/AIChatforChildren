#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\ChildReport;
use Core\Config;
use Utils\ChildReportService;

date_default_timezone_set(
    Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'))
);

$callModel = !in_array('--no-model', $argv, true);
$showPrompt = in_array('--show-prompt', $argv, true);
$showRaw = in_array('--show-raw', $argv, true);

$stubReportModel = new class extends ChildReport {
    public function __construct()
    {
    }
};

$service = new ChildReportService($stubReportModel);

$fixture = [
    'child' => [
        'birth_date' => '2015-09-18',
        'gender' => 'female',
        'last_login_at' => '2026-04-08 20:12:00',
    ],
    'scope' => [
        'type' => 'manual_incremental',
        'start_at' => '2026-04-04 08:00:00',
        'end_at' => '2026-04-08 20:12:00',
        'start_date' => '2026-04-04',
        'end_date' => '2026-04-08',
        'days' => 5,
        'report_day' => '2026-04-08',
    ],
    'summary' => [
        'total_logins' => 5,
        'total_conversations' => 4,
        'total_messages' => 24,
        'total_child_messages' => 12,
        'total_assistant_messages' => 12,
        'total_minutes' => 46,
        'active_days' => 4,
        'average_minutes_per_active_day' => 12,
        'average_messages_per_active_day' => 3,
        'last_login_at' => '2026-04-08T20:12:00+08:00',
        'peak_day' => '2026-04-06',
        'peak_score' => 10,
    ],
    'messages' => [
        [
            'conversation_id' => 2001,
            'role' => 'user',
            'content' => '今天在学校有人笑我画画很丑，我一整节课都在想这件事，后来都有点不想去学校了。',
            'created_at' => '2026-04-04 16:10:00',
        ],
        [
            'conversation_id' => 2001,
            'role' => 'assistant',
            'content' => '听起来你今天被同学的话伤到了。你愿意说说当时发生了什么吗？',
            'created_at' => '2026-04-04 16:10:20',
        ],
        [
            'conversation_id' => 2001,
            'role' => 'user',
            'content' => '他们午饭也没叫我，我觉得很尴尬，好像大家都不想跟我一组。',
            'created_at' => '2026-04-04 16:11:02',
        ],
        [
            'conversation_id' => 2001,
            'role' => 'assistant',
            'content' => '被排除会很难受。有没有一个同学是你相对敢靠近或愿意先聊天的？',
            'created_at' => '2026-04-04 16:11:28',
        ],
        [
            'conversation_id' => 2001,
            'role' => 'user',
            'content' => '我脑子里一直想他们是不是都讨厌我，是不是我真的很差。',
            'created_at' => '2026-04-04 16:12:09',
        ],
        [
            'conversation_id' => 2001,
            'role' => 'assistant',
            'content' => '你现在像是在把一次受伤的经历变成对自己整体的评价。我们可以慢一点拆开看。',
            'created_at' => '2026-04-04 16:12:34',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'user',
            'content' => '昨晚睡不好，一直担心明天小组作业又被嫌弃，早上起来肚子也有点不舒服。',
            'created_at' => '2026-04-06 07:45:00',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'assistant',
            'content' => '睡不好说明这件事已经让你紧张了很久。今天最担心出现的场面是什么？',
            'created_at' => '2026-04-06 07:45:25',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'user',
            'content' => '有一瞬间我想要是他们也倒霉就好了，但我没有真的想去打人。',
            'created_at' => '2026-04-06 07:46:01',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'assistant',
            'content' => '你是在描述生气时闪过的念头，不等于你真的会去做。先想想怎样让自己慢下来。',
            'created_at' => '2026-04-06 07:46:24',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'user',
            'content' => '我总觉得明天也会一样糟，然后我会更丢脸。',
            'created_at' => '2026-04-06 07:47:08',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'assistant',
            'content' => '那种“肯定会更糟”的想法会让压力放大。我们可以先准备一个更稳的应对办法。',
            'created_at' => '2026-04-06 07:47:39',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'user',
            'content' => '我其实想跟妈妈说，但又怕她觉得我太脆弱。',
            'created_at' => '2026-04-06 07:48:12',
        ],
        [
            'conversation_id' => 2002,
            'role' => 'assistant',
            'content' => '愿意考虑告诉妈妈本身就是在找支持，不是脆弱。',
            'created_at' => '2026-04-06 07:48:31',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'user',
            'content' => '今天体育课好一点，有个同学愿意跟我一组。',
            'created_at' => '2026-04-07 18:20:00',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'assistant',
            'content' => '这是个不错的变化，说明不是所有关系都在往坏的方向走。',
            'created_at' => '2026-04-07 18:20:21',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'user',
            'content' => '我还是会突然紧张，怕说错话，如果有人再笑我我怕会控制不住发火，然后事情会更糟。',
            'created_at' => '2026-04-07 18:21:02',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'assistant',
            'content' => '你已经注意到触发点了。提前想一个离开冲突或找老师的办法，会比硬撑更稳。',
            'created_at' => '2026-04-07 18:21:28',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'user',
            'content' => '我想先跟妈妈和班主任讲一讲，不然我老是自己乱想。',
            'created_at' => '2026-04-07 18:22:05',
        ],
        [
            'conversation_id' => 2003,
            'role' => 'assistant',
            'content' => '这是很实际的计划，也能让你不用一个人扛着。',
            'created_at' => '2026-04-07 18:22:24',
        ],
        [
            'conversation_id' => 2004,
            'role' => 'user',
            'content' => '周末我还想继续画画，其实画画会让我安静一点。',
            'created_at' => '2026-04-08 20:09:00',
        ],
        [
            'conversation_id' => 2004,
            'role' => 'assistant',
            'content' => '画画像是你的调节办法之一，可以把它当成让自己恢复稳定的小习惯。',
            'created_at' => '2026-04-08 20:09:26',
        ],
        [
            'conversation_id' => 2004,
            'role' => 'user',
            'content' => '我也不想惹麻烦，只是想有人认真听我说，别一下子觉得都是我的问题。',
            'created_at' => '2026-04-08 20:10:02',
        ],
        [
            'conversation_id' => 2004,
            'role' => 'assistant',
            'content' => '你很清楚自己真正想要的是被理解和被支持，这很重要。',
            'created_at' => '2026-04-08 20:10:31',
        ],
    ],
];

$result = $service->runPromptSmokeTest($fixture, $callModel);

$output = [
    'prompt_version' => $result['prompt_version'] ?? null,
    'call_model' => $callModel,
    'readiness' => $result['readiness'] ?? null,
    'validation' => $result['validation'] ?? null,
    'failure_reason' => $result['failure_reason'] ?? null,
    'analysis' => $callModel
        ? ($result['normalized_analysis'] ?? ($result['raw_analysis'] ?? null))
        : ($result['preview_analysis'] ?? null),
];

if ($showPrompt) {
    $output['system_prompt'] = $result['system_prompt'] ?? null;
    $output['user_packet'] = $result['user_packet'] ?? null;
}

if (!$callModel) {
    $output['analysis_preview'] = $result['preview_analysis'] ?? null;
}

if ($showRaw) {
    $output['raw_analysis'] = $result['raw_analysis'] ?? null;
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
