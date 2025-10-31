<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
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
                'null' => false,
                'unique' => true,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'unique' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'unique' => true,
            ],
            'auth_data' => [
                'type' => 'JSONB',
                'null' => false,
            ],
            'profile_data' => [
                'type' => 'JSONB',
                'null' => true,
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
        $this->forge->createTable('users');
        
        $this->db->query('CREATE INDEX idx_users_auth_data ON users USING GIN(auth_data)');
        $this->db->query('CREATE INDEX idx_users_profile_data ON users USING GIN(profile_data)');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
