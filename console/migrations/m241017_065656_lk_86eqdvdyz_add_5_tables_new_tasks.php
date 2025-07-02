<?php

use console\components\migration\Migration;

class m241017_065656_lk_86eqdvdyz_add_5_tables_new_tasks extends Migration
{
    public function up()
    {
        $this->createTable('tasks_projects', [
            'id'          => self::PRIMARY_KEY,
            'title'       => self::VARCHAR_FIELD,
            'icon'       => self::VARCHAR_FIELD,
            'order_index'  => self::INT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD
        ]);

        $this->createTable('tasks_publish_place', [
            'id'          => self::PRIMARY_KEY,
            'title'       => self::VARCHAR_FIELD,
            'order_index'  => self::INT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD
        ]);

        $this->createTable('tasks_tags', [
            'id'          => self::PRIMARY_KEY,
            'title'       => self::VARCHAR_FIELD,
            'color_hex'   => self::VARCHAR_FIELD,
            'order_index' => self::INT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD
        ]);

        $this->createTable('tasks', [
            'id'          => self::PRIMARY_KEY,
            'image'       => self::VARCHAR_FIELD,
            'name'        => self::VARCHAR_FIELD,
            'short_name'  => self::VARCHAR_FIELD,
            'tasks_publish_place_id' => self::INT_FIELD,
            'tasks_projects_id' => self::INT_FIELD,
            'date_start' => self::TIMESTAMP_FIELD,
            'date_end' => self::TIMESTAMP_FIELD,
            'description'  => self::VARCHAR_FIELD,
            'amount' => self::INT_FIELD,
            'amount_icon'  => self::VARCHAR_FIELD,
            'additional_text'  => self::VARCHAR_FIELD,
            'url_text'  => self::VARCHAR_FIELD,
            'url_link'  => self::VARCHAR_FIELD,
            'button_text'  => self::VARCHAR_FIELD,
            'button_url'  => self::VARCHAR_FIELD,
            'reward_amount_signature'  => self::VARCHAR_FIELD,
            'additional_explanation'  => self::VARCHAR_FIELD,
            'additional_url_text'  => self::VARCHAR_FIELD,
            'additional_url_link'  => self::VARCHAR_FIELD,
            'is_email_field'  => self::TINYINT_1_FIELD,
            'is_check_method_auto'  => self::TINYINT_1_FIELD,
            'is_permanent'  => self::TINYINT_1_FIELD,
            'is_publish'  => self::TINYINT_1_FIELD,
            'order_index' => self::INT_FIELD,
            'system_check_code'  => self::VARCHAR_FIELD,
            'created_at' => self::TIMESTAMP_FIELD
        ]);
        $this->createIndex('tasks_tasks_publish_place_id',   'tasks', 'tasks_publish_place_id');
        $this->createIndex('tasks_tasks_projects_id',   'tasks', 'tasks_projects_id');

        $this->addForeignKey('fk_tasks_tasks_publish_place_id', 'tasks',
            'tasks_publish_place_id', 'tasks_publish_place', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_tasks_tasks_projects_id', 'tasks',
            'tasks_projects_id', 'tasks_projects', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('tasks_tags_appointments', [
            'id'          => self::PRIMARY_KEY,
            'task_id'     => self::INT_FIELD,
            'tag_id'      => self::INT_FIELD,
            'created_at'  => self::TIMESTAMP_FIELD
        ]);

        $this->createIndex('tasks_tags_appointments_task_id_index',   'tasks_tags_appointments', 'task_id');
        $this->createIndex('tasks_tags_appointments_tag_id_index',   'tasks_tags_appointments', 'tag_id');
        $this->addForeignKey('fk_tasks_tags_appointments_tag_id', 'tasks_tags_appointments',
            'tag_id', 'tasks_tags', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_tasks_tags_appointments_task_id', 'tasks_tags_appointments',
            'task_id', 'tasks', 'id', 'CASCADE', 'CASCADE');

    }

    public function down()
    {

    }
}
