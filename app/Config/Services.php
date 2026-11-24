<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Servicios de la aplicación (documento maestro 9.2): auth, tenant, audit y uno por dominio (academic…);
 */
class Services extends BaseService
{
    public static function auth(bool $getShared = true): \App\Libraries\Auth
    {
        return $getShared ? static::getSharedInstance('auth') : new \App\Libraries\Auth();
    }

    public static function tenant(bool $getShared = true): \App\Libraries\Tenant
    {
        return $getShared ? static::getSharedInstance('tenant') : new \App\Libraries\Tenant();
    }

    public static function audit(bool $getShared = true): \App\Libraries\Audit
    {
        return $getShared ? static::getSharedInstance('audit') : new \App\Libraries\Audit();
    }

    public static function academic(bool $getShared = true): \App\Libraries\Academic
    {
        return $getShared ? static::getSharedInstance('academic') : new \App\Libraries\Academic();
    }

    public static function content(bool $getShared = true): \App\Libraries\Content
    {
        return $getShared ? static::getSharedInstance('content') : new \App\Libraries\Content();
    }

    public static function activities(bool $getShared = true): \App\Libraries\Activities
    {
        return $getShared ? static::getSharedInstance('activities') : new \App\Libraries\Activities();
    }

    public static function quizzes(bool $getShared = true): \App\Libraries\Quizzes
    {
        return $getShared ? static::getSharedInstance('quizzes') : new \App\Libraries\Quizzes();
    }

    public static function reports(bool $getShared = true): \App\Libraries\Reports
    {
        return $getShared ? static::getSharedInstance('reports') : new \App\Libraries\Reports();
    }

    public static function banks(bool $getShared = true): \App\Libraries\Banks
    {
        return $getShared ? static::getSharedInstance('banks') : new \App\Libraries\Banks();
    }

    public static function ai(bool $getShared = true): \App\Libraries\Ai\Gateway
    {
        return $getShared ? static::getSharedInstance('ai') : new \App\Libraries\Ai\Gateway();
    }

    public static function aiQuota(bool $getShared = true): \App\Libraries\Ai\Quota
    {
        return $getShared ? static::getSharedInstance('aiQuota') : new \App\Libraries\Ai\Quota();
    }

    public static function assistant(bool $getShared = true): \App\Libraries\Ai\Assistant
    {
        return $getShared ? static::getSharedInstance('assistant') : new \App\Libraries\Ai\Assistant();
    }

    public static function network(bool $getShared = true): \App\Libraries\Network
    {
        return $getShared ? static::getSharedInstance('network') : new \App\Libraries\Network();
    }

    public static function networkComms(bool $getShared = true): \App\Libraries\NetworkComms
    {
        return $getShared ? static::getSharedInstance('networkComms') : new \App\Libraries\NetworkComms();
    }

    public static function students(bool $getShared = true): \App\Libraries\Students
    {
        return $getShared ? static::getSharedInstance('students') : new \App\Libraries\Students();
    }

    public static function myProgress(bool $getShared = true): \App\Libraries\StudentProgress
    {
        return $getShared ? static::getSharedInstance('myProgress') : new \App\Libraries\StudentProgress();
    }

    public static function guardians(bool $getShared = true): \App\Libraries\Guardians
    {
        return $getShared ? static::getSharedInstance('guardians') : new \App\Libraries\Guardians();
    }

    public static function search(bool $getShared = true): \App\Libraries\Search
    {
        return $getShared ? static::getSharedInstance('search') : new \App\Libraries\Search();
    }

    public static function notify(bool $getShared = true): \App\Libraries\Notifier
    {
        return $getShared ? static::getSharedInstance('notify') : new \App\Libraries\Notifier();
    }

    public static function templates(bool $getShared = true): \App\Libraries\Templates
    {
        return $getShared ? static::getSharedInstance('templates') : new \App\Libraries\Templates();
    }

    public static function resources(bool $getShared = true): \App\Libraries\Resources
    {
        return $getShared ? static::getSharedInstance('resources') : new \App\Libraries\Resources();
    }

    public static function messages(bool $getShared = true): \App\Libraries\Messages
    {
        return $getShared ? static::getSharedInstance('messages') : new \App\Libraries\Messages();
    }

    public static function news(bool $getShared = true): \App\Libraries\News
    {
        return $getShared ? static::getSharedInstance('news') : new \App\Libraries\News();
    }

    public static function schoolSite(bool $getShared = true): \App\Libraries\SchoolSite
    {
        return $getShared ? static::getSharedInstance('schoolSite') : new \App\Libraries\SchoolSite();
    }

    public static function events(bool $getShared = true): \App\Libraries\Events
    {
        return $getShared ? static::getSharedInstance('events') : new \App\Libraries\Events();
    }

    public static function help(bool $getShared = true): \App\Libraries\HelpCenter
    {
        return $getShared ? static::getSharedInstance('help') : new \App\Libraries\HelpCenter();
    }

    public static function demo(bool $getShared = true): \App\Libraries\Demo
    {
        return $getShared ? static::getSharedInstance('demo') : new \App\Libraries\Demo();
    }

    public static function notebookFiles(bool $getShared = true): \App\Libraries\NotebookFiles
    {
        return $getShared ? static::getSharedInstance('notebookFiles') : new \App\Libraries\NotebookFiles();
    }

    public static function locale(bool $getShared = true): \App\Libraries\Locale
    {
        return $getShared ? static::getSharedInstance('locale') : new \App\Libraries\Locale();
    }

    public static function impersonation(bool $getShared = true): \App\Libraries\Impersonation
    {
        return $getShared ? static::getSharedInstance('impersonation') : new \App\Libraries\Impersonation();
    }

    public static function helpAssistant(bool $getShared = true): \App\Libraries\HelpAssistant
    {
        return $getShared ? static::getSharedInstance('helpAssistant') : new \App\Libraries\HelpAssistant();
    }

    public static function teachers(bool $getShared = true): \App\Libraries\Teachers
    {
        return $getShared ? static::getSharedInstance('teachers') : new \App\Libraries\Teachers();
    }

    public static function rbac(bool $getShared = true): \App\Libraries\Rbac
    {
        return $getShared ? static::getSharedInstance('rbac') : new \App\Libraries\Rbac();
    }

    public static function mfa(bool $getShared = true): \App\Libraries\Mfa
    {
        return $getShared ? static::getSharedInstance('mfa') : new \App\Libraries\Mfa();
    }

    public static function agenda(bool $getShared = true): \App\Libraries\Agenda
    {
        return $getShared ? static::getSharedInstance('agenda') : new \App\Libraries\Agenda();
    }

    public static function studentPreview(bool $getShared = true): \App\Libraries\StudentPreview
    {
        return $getShared ? static::getSharedInstance('studentPreview') : new \App\Libraries\StudentPreview();
    }

}
