<?php
/**
 * Arkham Files · Strings PT-BR
 *
 * Convenções:
 *   - common.*    → reutilizadas em vários contextos
 *   - admin.*     → área administrativa
 *   - public.*    → páginas públicas (scan)
 *   - errors.*    → páginas de erro e validação
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
        'confirm'          => 'Confirmar',
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
        'never'            => 'Nunca',

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
            'forgot'            => 'Esqueci minha senha',
            'next_step'         => 'próxima etapa · token de autenticação [ TOTP ]',
            'logs_retention'    => 'Registros de acesso mantidos · 30 dias',
            'build_tag'         => 'Containment Build',
        ],

        'forgot' => [
            'page_title'    => 'Recuperação de senha',
            'kicker'        => '◆ ━━ RECUPERAÇÃO DE ACESSO ━━ ◆',
            'title'         => 'Acesso comprometido',
            'subtitle'      => 'Procedimento manual obrigatório',
            'body'          => 'Esta instalação <strong>não possui recuperação automática por e-mail</strong>. Para redefinir sua chave, entre em contato direto com o <strong>Curador-chefe</strong>. Após confirmação, uma chave temporária será emitida e deverá ser substituída no primeiro acesso.',
            'back_to_login' => 'Retornar ao acesso',
        ],

        'change_password' => [
            'page_title'      => 'Trocar senha',
            'kicker'          => '◆ ━━ DEFINIR NOVA CHAVE DE ACESSO ━━ ◆',
            'title'           => 'Definir nova chave',
            'forced_reason'   => 'Sua senha foi redefinida pelo Curador-chefe. <strong>Defina uma nova chave</strong> para prosseguir.',
            'voluntary_reason'=> 'Defina uma nova chave de acesso. A senha atual será invalidada após confirmação.',
            'current_label'   => 'Senha atual',
            'new_label'       => 'Nova senha',
            'confirm_label'   => 'Confirmar nova senha',
            'submit'          => 'Atualizar chave',
            'success'         => 'Chave de acesso atualizada com sucesso.',
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
            'profile'  => 'Meu dossiê',
            'users'    => 'Curadores',
        ],

        'user' => [
            'role_admin'    => 'Admin',
            'role_curator'  => 'Curador',
            'status_active' => 'ativo',
        ],

        'profile' => [
            'page_title'       => 'Meu dossiê',
            'kicker'           => '━━ FICHA PESSOAL ━━',
            'username_label'   => 'Identificação',
            'email_label'      => 'E-mail de contato',
            'role_label'       => 'Papel',
            'created_label'    => 'Cadastrado em',
            'last_login_label' => 'Último acesso',
            'change_password'  => 'Alterar senha',
            'no_email'         => 'sem e-mail',
            'no_login_yet'     => 'nenhum acesso registrado',
        ],

        'users' => [
            'page_title'        => 'Curadores',
            'kicker'            => '━━ REGISTRO DE CURADORES ━━',
            'btn_new'           => '+ Novo curador',
            'col_username'      => 'Identificação',
            'col_email'         => 'E-mail',
            'col_role'          => 'Papel',
            'col_status'        => 'Status',
            'col_last_login'    => 'Último acesso',
            'col_actions'       => 'Ações',
            'action_edit'       => 'Editar',
            'action_reset'      => 'Resetar senha',
            'action_disable'    => 'Desabilitar',
            'action_enable'     => 'Reabilitar',
            'no_email'          => '—',
            'never_logged'      => 'nunca',

            'new_page_title'    => 'Novo curador',
            'new_kicker'        => '━━ NOVO REGISTRO DE CURADOR ━━',
            'new_help'          => 'A senha temporária será exibida uma única vez após criação. Anote e entregue ao curador.',

            'edit_page_title'   => 'Editar curador',
            'edit_kicker'       => '━━ ATUALIZAR DOSSIÊ ━━',

            'reset_page_title'  => 'Resetar senha',
            'reset_kicker'      => '━━ RESET DE CHAVE DE ACESSO ━━',
            'reset_confirm_title' => 'Confirmar reset',
            'reset_confirm_body'  => 'Esta ação invalidará a senha atual de <strong>:user</strong>, gerará uma nova senha temporária visível apenas uma vez e forçará o curador a definir nova senha no próximo acesso.',
            'reset_consequence_1' => 'invalidará a senha atual',
            'reset_consequence_2' => 'gerará uma nova senha temporária visível apenas uma vez',
            'reset_consequence_3' => 'forçará o curador a definir nova senha no próximo acesso',
            'reset_btn'           => 'Resetar agora',

            'reset_done_title'    => 'Senha temporária emitida',
            'reset_done_help'     => 'Anote a senha abaixo e entregue ao curador. <strong>Não será exibida novamente.</strong>',
            'reset_done_field'    => 'Senha temporária',
            'reset_done_back'     => 'Voltar ao registro',

            'created_title'       => 'Curador registrado',
            'created_help'        => 'Curador criado com sucesso. A senha abaixo é temporária e deve ser entregue ao novo usuário. <strong>Não será exibida novamente.</strong>',

            'flash_disabled'      => 'Curador :user desabilitado.',
            'flash_enabled'       => 'Curador :user reabilitado.',
            'flash_updated'       => 'Curador :user atualizado.',

            'form_username'       => 'Identificação',
            'form_username_help'  => 'Sem espaços. Não poderá ser alterada depois.',
            'form_email'          => 'E-mail (opcional)',
            'form_role'           => 'Papel',
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
            'subtitle' => 'Credenciais insuficientes para esta área',
        ],
        'wip' => [
            'kicker'   => 'Em construção',
            'title'    => 'Em breve',
            'subtitle' => 'Funcionalidade em desenvolvimento',
        ],

        'auth' => [
            'invalid_credentials'    => 'Identificação ou chave de acesso incorretas.',
            'rate_limited'           => 'Excesso de tentativas. Aguarde :seconds s antes de tentar novamente.',
            'account_locked'         => 'Conta temporariamente bloqueada. Aguarde :seconds s.',
            'account_disabled'       => 'Conta desabilitada. Contate o Curador-chefe.',
            'csrf_invalid'           => 'Token de segurança inválido. Recarregue a página e tente novamente.',
            'session_expired'        => 'Sessão expirada. Autentique-se novamente.',
            'wrong_current_password' => 'Senha atual incorreta.',
            'passwords_dont_match'   => 'A nova senha e a confirmação não coincidem.',
        ],

        'password' => [
            'too_short'         => 'A senha precisa ter no mínimo 8 caracteres.',
            'no_uppercase'      => 'Inclua ao menos uma letra maiúscula.',
            'no_lowercase'      => 'Inclua ao menos uma letra minúscula.',
            'no_digit'          => 'Inclua ao menos um número.',
            'no_symbol'         => 'Inclua ao menos um símbolo (ex: !@#$%&*).',
            'equals_username'   => 'A senha não pode ser igual à identificação.',
            'req_length'        => 'Mínimo 8 caracteres',
            'req_uppercase'     => '1 letra maiúscula',
            'req_lowercase'     => '1 letra minúscula',
            'req_digit'         => '1 número',
            'req_symbol'        => '1 símbolo (!@#$%&*…)',
        ],

        'users' => [
            'username_taken'         => 'Identificação já em uso.',
            'cant_disable_self'      => 'Você não pode desabilitar sua própria conta.',
            'cant_demote_last_admin' => 'Não é possível remover privilégios de admin do único Curador-chefe.',
            'invalid_role'           => 'Papel inválido.',
            'not_found'              => 'Curador não encontrado.',
            'invalid_email'          => 'E-mail inválido.',
            'invalid_username'       => 'Identificação inválida (use letras, números, ponto, hífen, underline).',
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
