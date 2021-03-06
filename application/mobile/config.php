<?php


return [
 	'template' => [
    // 模板布局开关
    'layout_on'    => false,
    // 模板布局文件
    'layout_name'  => 'base',
	],
	'controller_suffix'      => false,
	'view_replace_str'  =>  [
        '__ADMIN_STATIC__' => '/static/admin',
	],
	/** 报销科目*/
	'apply_subject' => [
		'职员餐费',
		'通讯费',
		'业务招待费',
		'人员工资',
		'福利费',
		'工程保险费',
		'消防设施费',
		'税费',
		'差率费',
		'办公用品',
		'水电费',
		'其他'
	],
];
