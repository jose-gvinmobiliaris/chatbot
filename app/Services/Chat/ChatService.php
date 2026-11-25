<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use App\Services\WordPress\WordPressProjectService;
use InvalidArgumentException;

class ChatService
{
    public function __construct(
        private LeadService $leadService,
        private ConversationService $conversationService,
        private MessageService $messageService,
        private WordPressProjectService $wordpressProjects
    ) {}

    public function start(?string $token = null): array
    {
        if ($token) {
            $conversation = $this->conversationService
                ->recoverConversation($token);

            if ($conversation) {
                if (!$conversation->flow_state) {
                    $conversation->flow_state = 'main_menu';
                    $conversation->save();
                }

                return [
                    'conversation' => $conversation,
                    'token' => $conversation->session_token,
                    'is_new' => false,
                ];
            }
        }

        $conversation = $this->conversationService->createConversation();

        $conversation->flow_state = 'main_menu';
        $conversation->flow_data = [];
        $conversation->save();

        $this->messageService->createMessage(
            $conversation,
            'bot',
            "¡Hola! Bienvenido al asistente virtual de GV Inmobiliaria.\n\n¿En qué podemos ayudarte?\n\n1. Buscar proyectos\n2. Preguntas frecuentes\n3. Hablar con un asesor"
        );

        return [
            'conversation' => $conversation->load('messages'),
            'token' => $conversation->session_token,
            'is_new' => true,
        ];
    }

    public function registerLead(
        Conversation $conversation,
        array $data
    ): Lead {
        return DB::transaction(function () use ($conversation, $data) {
            $lead = $this->leadService->createOrUpdate($data);

            $this->conversationService->attachLead(
                $conversation,
                $lead
            );

            return $lead;
        });
    }

    public function captureContactDetails(
        Conversation $conversation,
        string $phone,
        string $email
    ): array {

        if ($conversation->flow_state !== 'capture_phone') {
            throw new InvalidArgumentException(
                'La conversación no está esperando datos de contacto.'
            );
        }

        $data = $conversation->flow_data ?? [];

        // Registrar el lead y vincularlo con la conversación.
        $this->registerLead($conversation, [
            'name' => $data['lead_name'] ?? null,
            'phone' => $phone,
            'email' => strtolower(trim($email)),
        ]);

        // Guardar los datos capturados en la conversación.
        $data['lead_phone'] = $phone;
        $data['lead_email'] = strtolower(trim($email));

        $conversation->flow_data = $data;

        return $this->reply(
            $conversation,
            '¡Muchas gracias! Hemos registrado tus datos y tu interés. Un asesor de GV Inmobiliaria se comunicará contigo pronto.',
            'completed'
        );
    }

    public function sendMessage(
        Conversation $conversation,
        string $content,
        string $role = 'client'
    ) {
        return $this->messageService->createMessage(
            $conversation,
            $role,
            $content
        );
    }

    public function recommendProjects(array $filters = []): array
    {
        return $this->wordpressProjects->recommend($filters);
    }

    public function registerProjectInterest(
        Conversation $conversation,
        int $wordpressProjectId
    ): void {
        $project = $this->wordpressProjects->getProject(
            $wordpressProjectId
        );

        if (!$project) {
            throw new \InvalidArgumentException(
                'El proyecto seleccionado no existe en WordPress.'
            );
        }

        DB::table('conversation_project')->insertOrIgnore([
            'conversation_id' => $conversation->id,
            'wordpress_project_id' => $wordpressProjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Procesar los mensajes según el estado de la conversación.
     */
    public function processMessage(
        Conversation $conversation,
        string $content
    ): array {
        $content = trim($content);

        $this->sendMessage($conversation, $content, 'client');

        $state = $conversation->flow_state ?? 'main_menu';
        $data = $conversation->flow_data ?? [];

        switch ($state) {
            case 'main_menu':
                if ($content === '1') {
                    $conversation->flow_state = 'select_district';
                    $conversation->flow_data = [];
                    return $this->reply(
                        $conversation,
                        '¿En qué distrito estás buscando un inmueble? Escríbeme el nombre del distrito.'
                    );
                }

                if ($content === '2') {
                    return $this->reply(
                        $conversation,
                        'Estamos preparando nuestra sección de preguntas frecuentes. Por ahora, puedes elegir buscar proyectos o hablar con un asesor.'
                    );
                }

                if ($content === '3') {
                    return $this->reply(
                        $conversation,
                        '¡Claro! Déjanos tu nombre y teléfono para que un asesor pueda comunicarse contigo.',
                        'capture_name'
                    );
                }

                return $this->reply(
                    $conversation,
                    'Por favor, selecciona una opción:\n\n1. Buscar proyectos\n2. Preguntas frecuentes\n3. Hablar con un asesor'
                );

            case 'select_district':
                $data['district'] = $content;
                $conversation->flow_data = $data;
                $conversation->flow_state = 'select_property_type';

                return $this->reply(
                    $conversation,
                    "¿Qué tipo de inmueble buscas?\n\n1. Lotes\n2. Casas\n3. Departamentos"
                );

            case 'select_property_type':
                $types = [
                    '1' => 'lotes',
                    '2' => 'casas',
                    '3' => 'departamentos',
                ];

                if (!isset($types[$content])) {
                    return $this->reply(
                        $conversation,
                        'Selecciona una opción válida:\n\n1. Lotes\n2. Casas\n3. Departamentos'
                    );
                }

                $data['property_type'] = $types[$content];

                $projects = $this->recommendProjects([
                    'district' => $data['district'],
                    'property_type' => $data['property_type'],
                ]);

                if (empty($projects)) {
                    $conversation->flow_state = 'select_district';
                    $conversation->flow_data = [];

                    return $this->reply(
                        $conversation,
                        'No encontramos proyectos con esos criterios. Puedes intentar con otro distrito.'
                    );
                }

                $data['candidate_projects'] = array_values(
                    array_map(
                        function ($project) use ($data) {
                            return [
                                'id' => (int) $project['id'],
                                'name' => $project['name'],
                                'description' => $project['description'] ?? '',
                                'excerpt' => $project['excerpt'] ?? '',
                                'district' => $project['district'][0]['name']
                                    ?? $data['district'],
                                'price_from' => $project['price_from'] ?? null,
                                'area_from' => $project['area_from'] ?? null,
                                'initial_from' => $project['initial_from'] ?? null,
                                'image' => $project['image'] ?? null,
                                'url' => $project['url'] ?? null,
                            ];
                        },
                        array_slice($projects, 0, 5)
                    )
                );

                $conversation->flow_data = $data;
                $conversation->flow_state = 'select_project';
                $conversation->save();

                return $this->reply(
                    $conversation,
                    'Encontramos estos proyectos para ti. Selecciona el proyecto que te interesa y pulsa «Quiero información».',
                    null,
                    [
                        'projects' => $data['candidate_projects'],
                    ]
                );

            case 'select_project':
                return $this->reply(
                    $conversation,
                    'Selecciona el proyecto que te interesa pulsando «Quiero información».'
                );

            case 'capture_name':
                if (mb_strlen($content) < 2) {
                    return $this->reply(
                        $conversation,
                        'Por favor, escribe tu nombre.'
                    );
                }

                $data['lead_name'] = $content;
                $conversation->flow_data = $data;
                $conversation->flow_state = 'capture_phone';

                return $this->reply(
                    $conversation,
                    'Gracias. Ahora indícame tu número de celular para que nuestro equipo pueda comunicarse contigo.'
                );

            case 'capture_phone':

                return $this->reply(
                    $conversation,
                    'Para completar tu registro, utiliza el formulario e ingresa tu número de celular y correo electrónico.'
                );

            case 'completed':
                return $this->reply(
                    $conversation,
                    'Tus datos ya fueron registrados. Si necesitas ayuda adicional, puedes iniciar una nueva conversación.'
                );

            default:
                $conversation->flow_state = 'main_menu';
                $conversation->flow_data = [];

                return $this->reply(
                    $conversation,
                    'Volvamos al inicio. ¿En qué podemos ayudarte?\n\n1. Buscar proyectos\n2. Preguntas frecuentes\n3. Hablar con un asesor'
                );
        }
    }

    /**
     * Guardar y devolver la respuesta del bot.
     */
    private function reply(
        Conversation $conversation,
        string $content,
        ?string $newState = null,
        array $extra = []
    ): array {
        if ($newState) {
            $conversation->flow_state = $newState;
        }

        $conversation->last_interaction_at = now();
        $conversation->save();

        $message = $this->sendMessage(
            $conversation,
            $content,
            'bot'
        );

        return array_merge([
            'success' => true,
            'message' => $content,
            'flow_state' => $conversation->flow_state,
            'data' => $message,
        ], $extra);
    }

    public function selectProject(
        Conversation $conversation,
        int $wordpressProjectId
    ): array {
        if ($conversation->flow_state !== 'select_project') {
            return [
                'success' => false,
                'message' => 'No hay una selección de proyecto pendiente.',
            ];
        }

        $data = $conversation->flow_data ?? [];
        $projects = $data['candidate_projects'] ?? [];

        $selectedProject = null;

        foreach ($projects as $project) {
            if ((int) $project['id'] === $wordpressProjectId) {
                $selectedProject = $project;
                break;
            }
        }

        if (!$selectedProject) {
            return [
                'success' => false,
                'message' => 'El proyecto seleccionado no está disponible en esta búsqueda.',
            ];
        }

        // Registrar el interés en la base de datos.
        $this->registerProjectInterest(
            $conversation,
            $wordpressProjectId
        );

        // Guardar el proyecto seleccionado.
        $data['selected_project_id'] = $wordpressProjectId;

        $conversation->flow_data = $data;
        $conversation->flow_state = 'capture_name';
        $conversation->save();

        return $this->reply(
            $conversation,
            "¡Excelente elección! Has seleccionado {$selectedProject['name']}.\n\n¿Cuál es tu nombre?",
            null,
            [
                'selected_project' => $selectedProject,
            ]
        );
    }
}
