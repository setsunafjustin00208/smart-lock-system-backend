<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserLockPermissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INTEGER',
            ],
            'lock_id' => [
                'type' => 'INTEGER',
            ],
            'permissions' => [
                'type' => 'JSONB',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lock_id', 'locks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_lock_permissions');
        
        $this->db->query('CREATE UNIQUE INDEX idx_user_lock_unique ON user_lock_permissions(user_id, lock_id)');
    }

    public function down()
    {
        $this->forge->dropTable('user_lock_permissions');
    }
}
