<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'null' => false,
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'lock_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'lock_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'is_read' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'null' => false,
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
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lock_id', 'locks', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('notifications');

        // Create indexes for better performance
        $this->db->query('CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read)');
        $this->db->query('CREATE INDEX idx_notifications_created_at ON notifications(created_at)');
    }

    public function down()
    {
        $this->forge->dropTable('notifications');
    }
}
