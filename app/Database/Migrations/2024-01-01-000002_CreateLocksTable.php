<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLocksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'auto_increment' => true,
            ],
            'uuid' => [
                'type' => 'UUID',
                'null' => true,
                'unique' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'hardware_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'unique' => true,
            ],
            'config_data' => [
                'type' => 'JSONB',
                'null' => false,
            ],
            'status_data' => [
                'type' => 'JSONB',
                'null' => false,
            ],
            'is_online' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->createTable('locks');
        
        $this->db->query('CREATE INDEX idx_locks_config_data ON locks USING GIN(config_data)');
        $this->db->query('CREATE INDEX idx_locks_status_data ON locks USING GIN(status_data)');
    }

    public function down()
    {
        $this->forge->dropTable('locks');
    }
}
