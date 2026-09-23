<?php

namespace App\Enums;

/** Módulos liberados via perfil (lotação). */
enum Module: string
{
    case Triage = 'triage';
    case Attendance = 'attendance';
    case Monitor = 'monitor';
    case Panel = 'panel';
    case Reports = 'reports';
    case Scheduling = 'scheduling';
    case Customers = 'customers';
    case Users = 'users';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Triage => 'Triagem',
            self::Attendance => 'Atendimento',
            self::Monitor => 'Monitor',
            self::Panel => 'Painéis',
            self::Reports => 'Relatórios',
            self::Scheduling => 'Agendamentos',
            self::Customers => 'Clientes',
            self::Users => 'Usuários',
            self::Settings => 'Configurações da unidade',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Triage => 'Emissão e impressão de senhas',
            self::Attendance => 'Chamada e atendimento de senhas',
            self::Monitor => 'Acompanhamento das filas da unidade',
            self::Panel => 'Painéis de chamada da unidade',
            self::Reports => 'Estatísticas e relatórios',
            self::Scheduling => 'Agenda de atendimentos',
            self::Customers => 'Cadastro de clientes',
            self::Users => 'Atendentes da unidade',
            self::Settings => 'Serviços e contadores da unidade',
        };
    }

    public function route(): string
    {
        return 'modules.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Triage => 'ticket',
            self::Attendance => 'headset',
            self::Monitor => 'monitor',
            self::Panel => 'tv',
            self::Reports => 'chart',
            self::Scheduling => 'calendar',
            self::Customers => 'id-card',
            self::Users => 'users',
            self::Settings => 'cog',
        };
    }
}
