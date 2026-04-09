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
    'records' => [
        [
            'id' => 201,
            'generation_mode' => 'manual',
            'report_day' => '2026-03-18',
            'risk_level' => 'medium',
            'confidence' => 'low',
            'headline' => 'Child under peer stress with self-doubt',
            'scope_started_at' => '2026-03-01T00:00:00+08:00',
            'scope_ended_at' => '2026-03-18T19:20:00+08:00',
            'created_at' => '2026-03-18T19:20:00+08:00',
            'updated_at' => '2026-03-18T19:20:00+08:00',
        ],
        [
            'id' => 202,
            'generation_mode' => 'manual',
            'report_day' => '2026-04-08',
            'risk_level' => 'medium',
            'confidence' => 'low',
            'headline' => 'Child shows more coping and help-seeking',
            'scope_started_at' => '2026-03-19T00:00:00+08:00',
            'scope_ended_at' => '2026-04-08T20:12:00+08:00',
            'created_at' => '2026-04-08T20:12:00+08:00',
            'updated_at' => '2026-04-08T20:12:00+08:00',
        ],
    ],
    'reports' => [
        [
            'generation_mode' => 'manual',
            'risk_level' => 'medium',
            'generated_at' => '2026-03-18T19:20:00+08:00',
            'scope' => [
                'start_at' => '2026-03-01T00:00:00+08:00',
                'end_at' => '2026-03-18T19:20:00+08:00',
                'report_day' => '2026-03-18',
            ],
            'analysis' => [
                'headline' => 'Child under peer stress with self-doubt',
                'topic_overview' => 'Chat focused on feeling excluded, anticipating rejection, and doubting self-worth.',
                'emotional_overview' => [
                    'summary' => 'Mostly anxious and hurt tone with strong rumination after peer setbacks.',
                ],
                'wellbeing' => [
                    'summary' => 'Social stress was affecting confidence and sleep, with limited coping at that point.',
                ],
                'risk_dimensions' => [
                    [
                        'name' => 'Social-Emotional Distress',
                        'level' => 'medium',
                        'summary' => 'Peer exclusion was driving anxiety and harsh self-judgment.',
                    ],
                ],
                'thinking_patterns' => [
                    [
                        'name' => 'Overgeneralization',
                        'level' => 'medium',
                        'summary' => 'Specific peer incidents were interpreted as proof of being broadly disliked.',
                    ],
                ],
                'protective_factors' => [
                    [
                        'name' => 'Emotional Expression',
                        'summary' => 'The child was still willing to describe feelings in chat.',
                    ],
                ],
                'topics' => [
                    [
                        'name' => 'Peer Relationships',
                        'summary' => 'Repeated discussion of teasing, exclusion, and fear of rejection.',
                    ],
                ],
                'parent_guidance' => [
                    'Listen first and avoid correcting feelings too quickly.',
                    'Check in about school dynamics and who feels safe to talk to.',
                ],
            ],
        ],
        [
            'generation_mode' => 'manual',
            'risk_level' => 'medium',
            'generated_at' => '2026-04-08T20:12:00+08:00',
            'scope' => [
                'start_at' => '2026-03-19T00:00:00+08:00',
                'end_at' => '2026-04-08T20:12:00+08:00',
                'report_day' => '2026-04-08',
            ],
            'analysis' => [
                'headline' => 'Child shows more coping and help-seeking',
                'topic_overview' => 'Chat still reflects peer sensitivity, but also more planning, support-seeking, and positive coping.',
                'emotional_overview' => [
                    'summary' => 'Still anxious about peers, though more balanced and less stuck than before.',
                ],
                'wellbeing' => [
                    'summary' => 'Stress remains visible, but the child is showing more resilience and support-seeking.',
                ],
                'risk_dimensions' => [
                    [
                        'name' => 'Social-Emotional Distress',
                        'level' => 'medium',
                        'summary' => 'Peer stress still affects mood, though it no longer looks as overwhelming.',
                    ],
                    [
                        'name' => 'Negative Self-Perception',
                        'level' => 'medium',
                        'summary' => 'Harsh self-evaluation still appears during setbacks.',
                    ],
                ],
                'thinking_patterns' => [
                    [
                        'name' => 'Overgeneralization',
                        'level' => 'medium',
                        'summary' => 'Negative events can still expand into bigger beliefs about self-worth.',
                    ],
                    [
                        'name' => 'Catastrophic Thinking',
                        'level' => 'low',
                        'summary' => 'Future peer situations are still sometimes expected to go badly.',
                    ],
                ],
                'protective_factors' => [
                    [
                        'name' => 'Help-Seeking Orientation',
                        'summary' => 'The child has started planning to talk to a parent and teacher.',
                    ],
                    [
                        'name' => 'Positive Coping Strategies',
                        'summary' => 'Drawing and slowing down before school are being used as self-regulation tools.',
                    ],
                ],
                'topics' => [
                    [
                        'name' => 'Peer Relationships',
                        'summary' => 'Peer stress remains central, but some positive social moments are now present too.',
                    ],
                    [
                        'name' => 'Coping and Support',
                        'summary' => 'Chat increasingly includes coping plans and trusted-adult support.',
                    ],
                ],
                'parent_guidance' => [
                    'Reinforce the child asking for support as a strength.',
                    'Notice and encourage small positive peer experiences and coping gains.',
                ],
            ],
        ],
    ],
    'retained_messages' => [
        [
            'message_id' => 9001,
            'conversation_id' => 7001,
            'role' => 'user',
            'content' => '最近还是会担心同学怎么看我，不过比之前更愿意跟妈妈说了。',
            'created_at' => '2026-04-02 18:10:00',
        ],
        [
            'message_id' => 9002,
            'conversation_id' => 7001,
            'role' => 'assistant',
            'content' => '愿意告诉妈妈，说明你不是一个人扛着了。',
            'created_at' => '2026-04-02 18:10:15',
        ],
        [
            'message_id' => 9003,
            'conversation_id' => 7002,
            'role' => 'user',
            'content' => '我现在会先画画或者深呼吸，再决定要不要找老师帮忙。',
            'created_at' => '2026-04-07 19:20:00',
        ],
        [
            'message_id' => 9004,
            'conversation_id' => 7002,
            'role' => 'assistant',
            'content' => '你已经在主动给自己准备更稳的应对方式了。',
            'created_at' => '2026-04-07 19:20:18',
        ],
        [
            'message_id' => 9005,
            'conversation_id' => 7003,
            'role' => 'user',
            'content' => '虽然还是会乱想，但现在不像一开始那样觉得所有人都不喜欢我。',
            'created_at' => '2026-04-08 20:09:00',
        ],
        [
            'message_id' => 9006,
            'conversation_id' => 7003,
            'role' => 'assistant',
            'content' => '这说明你在慢慢看到事情不只是一个方向。',
            'created_at' => '2026-04-08 20:09:20',
        ],
    ],
];

$result = $service->runTrendPromptSmokeTest($fixture, $callModel);

$output = [
    'prompt_version' => $result['prompt_version'] ?? null,
    'call_model' => $callModel,
    'validation' => $result['validation'] ?? null,
    'analysis' => $callModel
        ? ($result['normalized_analysis'] ?? ($result['raw_analysis'] ?? null))
        : ($result['fallback_analysis'] ?? null),
];

if ($showPrompt) {
    $output['system_prompt'] = $result['system_prompt'] ?? null;
    $output['user_packet'] = $result['user_packet'] ?? null;
    $output['fallback_analysis'] = $result['fallback_analysis'] ?? null;
}

if (!$callModel) {
    $output['analysis_preview'] = $result['fallback_analysis'] ?? null;
}

if ($showRaw) {
    $output['raw_analysis'] = $result['raw_analysis'] ?? null;
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
