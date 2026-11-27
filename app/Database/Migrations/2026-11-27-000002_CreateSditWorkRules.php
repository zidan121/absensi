<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSditWorkRules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'schedule_name'=>['type'=>'VARCHAR','constraint'=>100,'default'=>'Jadwal SDIT'],
            'day_of_week'=>['type'=>'TINYINT','constraint'=>1],
            'start_time'=>['type'=>'TIME'],
            'end_time'=>['type'=>'TIME'],
            'late_after_minutes'=>['type'=>'SMALLINT','default'=>0],
            'is_workday'=>['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addUniqueKey(['schedule_name','day_of_week'],'uq_sdit_schedule_day');
        $this->forge->createTable('sdit_work_schedules',true);

        $this->forge->addField([
            'id'=>'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pin'=>['type'=>'VARCHAR','constraint'=>32],
            'day_of_week'=>['type'=>'TINYINT','constraint'=>1],
            'start_time'=>['type'=>'TIME'],
            'end_time'=>['type'=>'TIME'],
            'late_after_minutes'=>['type'=>'SMALLINT','default'=>0],
            'effective_from'=>['type'=>'DATE','null'=>true],
            'effective_until'=>['type'=>'DATE','null'=>true],
            'is_workday'=>['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey(['pin','day_of_week','effective_from'],'idx_sdit_employee_schedule');
        $this->forge->createTable('sdit_employee_schedules',true);

        $this->forge->addField([
            'id'=>'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'work_date'=>['type'=>'DATE'],
            'day_type'=>['type'=>'VARCHAR','constraint'=>30,'default'=>'holiday'],
            'description'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'allow_afternoon_entry'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addUniqueKey('work_date','uq_sdit_calendar_date');
        $this->forge->createTable('sdit_work_calendar',true);
    }

    public function down()
    {
        $this->forge->dropTable('sdit_work_calendar',true);
        $this->forge->dropTable('sdit_employee_schedules',true);
        $this->forge->dropTable('sdit_work_schedules',true);
    }
}
