<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGSERIAL',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'lock_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'details' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('lock_id', 'locks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('activity_logs');
        
        $this->db->query('CREATE INDEX idx_activity_logs_details ON activity_logs USING GIN(details)');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs');
    }
}
