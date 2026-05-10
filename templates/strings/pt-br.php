<?php
/**
 * Arkham Files · Strings PT-BR
 *
 * Convenções:
 *   - common.*    → reutilizadas em vários contextos
 *   - admin.*     → área administrativa
 *   - public.*    → páginas públicas (scan)
 *   - errors.*    → páginas de erro
 *   - placeholders → :name vira o valor de params['name']
 */

return [
    'common' => [
        'app_name'         => 'Arkham Files',
        'app_subtitle'     => 'QR Division',
        'save'             => 'Arquivar',
        'cancel'           => 'Cancelar',
        'edit'             => 'Editar',
        'delete'           => 'Excluir',
        'confirm_delete'   => 'Tem certeza?',
        'back'             => 'Retornar',
        'loading'          => 'Carregando…',
        'search'           => 'Consultar arquivos…',
        'yes'              => 'Sim',
        'no'               => 'Não',
        'no_results'       => 'Nenhum registro encontrado',
        'expires_never'    => 'Não expira',
        'expires_in_days'  => 'Expira em :days dias',
        'expired_on'       => 'Arquivado em :date',
        'permanent'        => 'Permanente',

        // Status visíveis em badges/listas
        'status_active'    => 'Ativo',
        'status_archived'  => 'Arquivado',
        'status_expiring'  => 'Expira',
        'status_disabled'  => 'Inativo',
    ],

    'admin' => [
        'login' => [
            'page_title'        => 'Acesso',
            'restricted_area'   => 'RESTRICTED AREA',
            'authorized_only'   => 'AUTHORIZED PERSONNEL ONLY',
            'username_label'    => 'Identificação',
            'password_label'    => 'Chave de acesso',
            'submit'            => 'Autorizar acesso',
            'next_step'         => 'próxima etapa · token de autenticação [ TOTP ]',
            'logs_retention'    => 'Registros de acesso mantidos · 30 dias',
            'build_tag'         => 'Containment Build',
        ],

        'two_factor' => [
            'page_title'      => 'Verificação',
            'second_step'     => '▲ SEGUNDA ETAPA DE AUTORIZAÇÃO ▲',
            'token_title'     => 'Token de autenticação',
            'token_help'      => 'Insira o código de 6 dígitos exibido em seu aplicativo autenticador.',
            'code_valid_for'  => 'Código válido por :seconds s',
            'submit'          => 'Autorizar acesso',
            'recovery_link'   => 'autenticador inacessível? usar código de recuperação',
        ],

        'two_factor_setup' => [
            'page_title'      => 'Configurar 2FA',
            'security_kicker' => '◆ ━━ PROTOCOLO DE SEGURANÇA ━━ ◆',
            'title'           => 'Ativar token de autenticação',
            'help'            => 'Configure o segundo fator antes de prosseguir. Recomendado: Aegis, Authy, Google Authenticator.',
            'step_1'          => 'Etapa 01',
            'step_1_help'     => 'Escaneie o código com seu autenticador.',
            'manual_key'      => 'Chave manual',
            'step_2'          => 'Etapa 02',
            'step_2_help'     => 'Confirme inserindo o código de 6 dígitos gerado pelo seu app.',
            'code_valid'      => '✓ Código válido',
            'submit'          => 'Ativar proteção',
            'warning'         => '⚠ guarde a chave manual em local seguro · códigos de recuperação serão emitidos após ativação',
            'restricted'      => '━━ ACESSO RESTRITO ATÉ ATIVAÇÃO COMPLETA ━━',
        ],

        'dashboard' => [
            'page_title'       => 'Dashboard',
            'archives_heading' => 'Arquivos',
            'no_category'      => 'Sem categoria',
            'stat_active'      => 'Ativos',
            'stat_expiring'    => 'Expirando',
            'stat_archived'    => 'Expirados',
            'stat_scans_24h'   => 'Scans / 24h',
            'btn_new'          => '+ Novo arquivo',
            'filter_type'      => 'Tipo',
            'filter_status'    => 'Status',
            'filter_subcats'   => '+ subcategorias',
            'col_type'         => 'Tipo',
            'col_dossier'      => 'Dossiê',
            'col_scans'        => 'Scans',
            'col_expires'      => 'Expira',
            'pagination_showing' => 'Exibindo :from–:to de :total',
        ],

        'sidebar' => [
            'logout'   => 'Encerrar sessão',
            'settings' => 'Configurações',
        ],

        'user' => [
            'role_curator' => 'Curador',
            'status_active' => 'ativo',
        ],
    ],

    'public' => [
        'viewer' => [
            'dossier_id'           => 'Dossiê Nº',
            'classification'       => 'Classificação',
            'class_botanical'      => 'Botânico · Restrito',
            'class_memorandum'     => 'Memorando',
            'class_photo'          => 'Dossiê Fotográfico',
            'timeline_title'       => 'Linha do tempo',
            'genetics_label'       => 'Genética',
            'origin_label'         => 'Origem',
            'type_label'           => 'Tipo',
            'planting'             => 'Plantio',
            'flowering'            => 'Floração',
            'harvest'              => 'Colheita',
            'cycle_total'          => 'Ciclo total',
            'days'                 => 'dias',
            'days_in_veg'          => '+:days dias em vegetação',
            'days_in_flower'       => '+:days dias em floração',
            'document_accessed_at' => 'Documento acessado em :datetime',
            'department_notes'     => 'Departamento de Memorandos',
            'department_photo'     => 'Arquivo Fotográfico',
            'department_botany'    => 'Botanical Containment Division',
            'btn_download'         => 'Solicitar cópia',
            'btn_zoom'             => 'Ampliar',
            'evidence_label'       => 'Evidência · :id',
            'image_dimensions'     => 'Dimensões',
            'image_size'           => 'Tamanho',
            'image_format'         => 'Formato',
        ],
    ],

    'errors' => [
        'not_found' => [
            'kicker'   => 'Arquivo inexistente',
            'title'    => 'Paciente não localizado',
            'subtitle' => 'Transferido para o Bloco H',
            'body'     => 'O dossiê solicitado não consta nos arquivos ativos. Verifique o código ou contate a curadoria.',
            'transfer_record' => 'Registro de transferência',
        ],
        'expired' => [
            'kicker'   => 'Caso encerrado',
            'title'    => 'Caso arquivado',
            'subtitle' => 'Documento removido do acervo',
            'body'     => 'Este dossiê foi removido do acervo público. Para consultas, contate a curadoria responsável.',
            'archived_on'    => 'Data de arquivamento',
            'retention_note' => 'Período de retenção: 90 dias · Código QR desativado · acesso público encerrado',
        ],
        'forbidden' => [
            'kicker'   => 'Acesso negado',
            'title'    => 'Autorização ausente',
            'subtitle' => 'Credenciais insuficientes',
        ],
        'wip' => [
            'kicker'   => 'Em construção',
            'title'    => 'Em breve',
            'subtitle' => 'Funcionalidade em desenvolvimento',
        ],
    ],

    'qr_types' => [
        'url'    => 'Link',
        'wifi'   => 'Wi-Fi',
        'vcard'  => 'Contato',
        'phone'  => 'Telefone',
        'sms'    => 'SMS',
        'email'  => 'E-mail',
        'maps'   => 'Localização',
        'social' => 'Social',
        'note'   => 'Nota',
        'strain' => 'Strain',
        'image'  => 'Imagem',
    ],

    'genetics' => [
        'indica'   => 'Indica',
        'sativa'   => 'Sativa',
        'hibrida'  => 'Híbrida',
    ],

    'sources' => [
        'semente' => 'Semente',
        'clone'   => 'Clone',
    ],

    'seed_types' => [
        'regular'    => 'Regular',
        'feminizada' => 'Feminizada',
        'automatica' => 'Automática',
    ],
];
