<?php

namespace Database\Migrations;

use Core\Migration;
use Utils\ChildPromptService;

class CreateChildPromptProfilesTable extends Migration
{
    public function up()
    {
        $service = new ChildPromptService($this->pdo);
        $service->ensureSchema();
        $created = $service->backfillMissingChildProfiles();

        echo " Child prompt profile table created successfully.\n";
        echo " Backfilled {$created} child prompt profile(s).\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS child_prompt_profiles");
        echo " Child prompt profile table dropped.\n";
    }
}
