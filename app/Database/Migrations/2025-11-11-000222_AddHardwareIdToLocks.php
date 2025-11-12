<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHardwareIdToLocks extends Migration
{
    public function up()
    {
        $this->forge->addColumn('locks', [
            'hardware_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'unique' => true,
                'after' => 'name'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('locks', 'hardware_id');
    }
}
