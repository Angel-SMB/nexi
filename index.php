<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

/**
 * ChatbotControllerGroq v3
 * ------------------------
 * Cadena de respuesta:
 *   1. Groq Cloud API directo (rápido, usa GROQ_API_KEY del .env)
 *   2. Proxy en Render (respaldo si Groq falla)
 *   3. Fallback local con respuestas predefinidas
 *
 * El conocimiento del asistente vive en getWebBridgeKnowledge()
 * — edítalo ahí cuando cambien precios, proyectos o servicios.
 */
class ChatbotControllerGroq extends ResourceController
{
    protected $format = 'json';

    private string $groqUrl   = 'https://api.groq.com/openai/v1/chat/completions';
    private string $groqModel = 'llama-3.3-70b-versatile';
    private string $renderUrl = 'https://nexi-71nf.onrender.com';

    public function message()
    {
        // HABILITAR HEADERS CORS
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit;
        }

        try {
            $userMessage = $this->request->getPost('message');
            $historyRaw  = $this->request->getPost('history');
            $conversationHistory = json_decode($historyRaw ?? '[]', true) ?: [];

            if (empty($userMessage)) {
                return $this->respond(['success' => false, 'error' => 'Mensaje vacío'], 400);
            }

            // 1) Groq directo
            $response = $this->callGroq($userMessage, $conversationHistory);

            // 2) Respaldo: proxy en Render
            if (!$response['success']) {
                log_message('warning', 'Groq directo falló, intentando Render: ' . ($response['error'] ?? '?'));
                $response = $this->callRenderProxy($userMessage, $conversationHistory);
            }

            if ($response['success']) {
                return $this->respond([
                    'success'   => true,
                    'message'   => $response['message'],
                    'mode'      => $response['mode'] ?? 'ai',
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);
            }

            // 3) Fallback local
            log_message('warning', 'IA no disponible, usando fallback local: ' . ($response['error'] ?? 'Unknown'));
            return $this->respond([
                'success'   => true,
                'message'   => $this->getSmartFallbackResponse($userMessage),
                'mode'      => 'fallback_local',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error en controlador: ' . $e->getMessage());
            return $this->respond([
                'success'   => true,
                'message'   => $this->getSmartFallbackResponse($userMessage ?? ''),
                'mode'      => 'fallback_exception',
                'timestamp' => date('Y-m-d H:i:s'),
            ], 200);
        }
    }

    /**
     * Llamada directa a Groq Cloud API con el conocimiento actualizado.
     */
    private function callGroq(string $userMessage, array $history): array
    {
        $apiKey = trim((string) (env('groq.apiKey') ?: env('GROQ_API_KEY') ?: ''));
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'GROQ_API_KEY no configurada'];
        }

        // Armar mensajes: system + últimos 10 turnos + mensaje actual
        $messages = [['role' => 'system', 'content' => $this->getInfinextKnowledge()]];

        $history = array_slice($history, -10);
        foreach ($history as $turn) {
            $role    = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => mb_substr($userMessage, 0, 2000)];

        $payload = [
            'model'       => $this->groqModel,
            'temperature' => 0.6,
            'max_tokens'  => 900,
            'messages'    => $messages,
        ];

        $ch = curl_init($this->groqUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode !== 200) {
            return ['success' => false, 'error' => "Groq HTTP {$httpCode}: {$curlErr}"];
        }

        $data    = json_decode($result, true);
        $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            return ['success' => false, 'error' => 'Respuesta vacía de Groq'];
        }

        return ['success' => true, 'message' => $content, 'mode' => 'ai_groq'];
    }

    /**
     * Enviar formulario de cotización o cita
     */
    public function sendQuoteRequest()
    {
        // CORS
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit;
        }

        try {
            $json = $this->request->getJSON(true);

            $nombre   = $json['nombre'] ?? '';
            $email    = $json['email'] ?? '';
            $telefono = $json['telefono'] ?? '';
            $tipo     = $json['tipo'] ?? 'cotización';
            $detalles = $json['detalles'] ?? '';

            if (empty($nombre) || empty($email)) {
                return $this->respond([
                    'success' => false,
                    'error'   => 'Nombre y email son obligatorios'
                ], 400);
            }

            $emailSent = $this->sendEmail($nombre, $email, $telefono, $tipo, $detalles);

            if ($emailSent) {
                return $this->respond([
                    'success' => true,
                    'message' => '¡Solicitud enviada correctamente! Te contactaremos pronto.'
                ]);
            }

            return $this->respond([
                'success' => false,
                'error'   => 'Error al enviar el email'
            ], 500);

        } catch (\Exception $e) {
            log_message('error', 'Error en sendQuoteRequest: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'error'   => 'Error del servidor'
            ], 500);
        }
    }

    /**
     * Enviar email con la solicitud
     */
    private function sendEmail($nombre, $email, $telefono, $tipo, $detalles)
    {
        try {
            $emailService = \Config\Services::email();

            $emailService->setFrom('infinexttechnologies@gmail.com', 'Nexi AI');
            $emailService->setTo('infinexttechnologies@gmail.com');
            $emailService->setSubject('Nueva Solicitud de ' . ucfirst($tipo) . ' desde Chatbot');

            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0f2a5e 0%, #1e3a8a 55%, #2d4fad 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                    .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                    .field { margin-bottom: 15px; padding: 10px; background: white; border-radius: 5px; }
                    .field strong { color: #0f2a5e; }
                    .footer { text-align: center; margin-top: 20px; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🤖 Nueva Solicitud desde Nexi AI</h2>
                    </div>
                    <div class='content'>
                        <p>Has recibido una nueva solicitud de <strong>" . ucfirst($tipo) . "</strong> a través del chatbot:</p>

                        <div class='field'>
                            <strong>👤 Nombre:</strong><br>
                            " . htmlspecialchars($nombre) . "
                        </div>

                        <div class='field'>
                            <strong>📧 Email:</strong><br>
                            <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a>
                        </div>

                        <div class='field'>
                            <strong>📞 Teléfono:</strong><br>
                            " . (!empty($telefono) ? htmlspecialchars($telefono) : 'No proporcionado') . "
                        </div>

                        <div class='field'>
                            <strong>📝 Tipo de Solicitud:</strong><br>
                            " . ucfirst($tipo) . "
                        </div>

                        <div class='field'>
                            <strong>💬 Detalles:</strong><br>
                            " . nl2br(htmlspecialchars($detalles)) . "
                        </div>

                        <div class='footer'>
                            <p>Este mensaje fue generado automáticamente por Nexi AI/p>
                            <p>📅 " . date('d/m/Y H:i:s') . "</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            $emailService->setMessage($message);

            if ($emailService->send()) {
                log_message('info', 'Email enviado correctamente a infinexttechnologies@gmail.com');
                return true;
            }

            log_message('error', 'Error al enviar email: ' . $emailService->printDebugger(['headers']));
            return false;

        } catch (\Exception $e) {
            log_message('error', 'Excepción al enviar email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Respaldo: proxy en Render (puede tardar si el servicio está dormido)
     */
    private function callRenderProxy(string $userMessage, array $conversationHistory): array
    {
        try {
            $data = [
                'message' => $userMessage,
                'history' => $conversationHistory
            ];

            $ch = curl_init($this->renderUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                return ['success' => false, 'error' => $error];
            }

            $response = json_decode($result, true);

            if ($httpCode === 200 && isset($response['success']) && $response['success'] === true) {
                return ['success' => true, 'message' => $response['message'], 'mode' => 'ai_render'];
            }

            return ['success' => false, 'error' => 'Status Code: ' . $httpCode];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

        private function getSmartFallbackResponse(string $message): string
    {
        $lowerMessage = strtolower($message);
        $lowerMessage = $this->removeAccents($lowerMessage);

        // PROYECTOS REALIZADOS
        if (preg_match('/(proyecto|portafolio|ejemplo|trabajo|inaoe|oasis|car madness|juego)/i', $lowerMessage)) {
            return "💼 **Proyectos Destacados de InfiNext:**\n\n" .
                "**1. Gestión de Laboratorios (INAOE)** 🔬\n" .
                "• Software de gestión y semi-automatización\n" .
                "• Interfaz 3D y control para eficiencia eléctrica\n\n" .
                "**2. Oasis AR** 📱\n" .
                "• App móvil educativa con Realidad Aumentada\n" .
                "• Exploración interactiva de culturas antiguas\n\n" .
                "**3. Car Madness** 🎮\n" .
                "• Videojuego 2D de plataformas y gamificación\n" .
                "• Capacitación en seguridad industrial\n\n" .
                "**4. Sistemas de Visión IA** 👁️\n" .
                "• Modelos de tracking en emergencias y detección de baches\n\n" .
                "**¿Te interesa desarrollar algo de este nivel?** 🚀";
        }

        // COTIZACIÓN / CITA
        if (preg_match('/(cotiz|presupuesto|agendar|cita|reunion|precio proyecto)/i', $lowerMessage)) {
            return "📋 **Solicitar Cotización o Asesoría**\n\n" .
                "¡Excelente decisión! Para darte la mejor atención, necesito unos datos rápidos:\n\n" .
                "**👤 Tu nombre**\n" .
                "**📧 Tu email**\n" .
                "**📞 Tu teléfono** (opcional)\n" .
                "**💬 Breve descripción de tu idea**\n\n" .
                "Por favor, proporciona esta información y te contactaremos en menos de 24 horas. 🚀\n\n" .
                "_(También puedes usar el botón 'Cotización' en las opciones del chat)_";
        }

        // SOBRE LA EMPRESA
        if (preg_match('/(que es|quienes son|sobre|acerca de).*(infinext|empresa)/i', $lowerMessage)) {
            return "🏢 **InfiNext** es una agencia de innovación tecnológica y desarrollo de software con sede en Puebla, México.\n\n" .
                "**Nuestra especialidad:**\n" .
                "• Desarrollo de Sistemas a la medida (Web, E-commerce, POS)\n" .
                "• Integración de Inteligencia Artificial y Visión Computacional\n" .
                "• Realidad Aumentada (AR) y Gamificación\n\n" .
                "¿Te gustaría conocer nuestras soluciones base?";
        }

        // UBICACIÓN
        if (preg_match('/(donde|ubicacion|direccion|oficina)/i', $lowerMessage)) {
            return "📍 **Ubicación y Alcance:**\n\n" .
                "Tenemos sede en **Puebla, México**, pero desarrollamos proyectos a nivel nacional e internacional.\n\n" .
                "**Contacto Directo:**\n" .
                "📞 WhatsApp: 2217596585\n" .
                "📧 infinexttechnologies@gmail.com\n" .
                "⏰ Lunes a Viernes, 3:00 PM - 9:00 PM";
        }

        // TECNOLOGIAS Y TIEMPOS
        if (preg_match('/(tecnologia|lenguaje|programa|tiempo|plazo|duracion)/i', $lowerMessage)) {
            return "⚡ **Tecnologías y Tiempos:**\n\n" .
                "**Stack Tecnológico:**\n" .
                "• **Web:** Laravel, CodeIgniter 4, Laravel, Inertia, Next.js, PHP, JS, MySQL, MongoDB\n" .
                "• **IA:** YOLO, MediaPipe, OpenCV\n" .
                "• **3D/AR:** Unity, C#, Vuforia, Blender\n\n" .
                "**Tiempos:** Dependen totalmente del alcance de tu proyecto. Siempre entregamos un cronograma claro antes de iniciar.\n\n" .
                "¿Qué tipo de solución tienes en mente?";
        }

        // PRECIOS Y PAQUETES
        if (preg_match('/(paquete|precio|costo|cuanto cuesta|plan|solucion)/i', $lowerMessage)) {
            return "📦 **Soluciones Base InfiNext:**\n\n" .
                "**1. Presencia Digital** — Desde \$4,000 MXN\n" .
                "• Landing page, se adapta a cualquier dispositivo, SEO básico.\n\n" .
                "**2. Sistemas y Gestión** ⭐ — Desde \$10,000 MXN\n" .
                "• Desarrollo backend, panel admin, e-commerce, POS.\n\n" .
                "**3. Innovación NextGen** — A la medida\n" .
                "• Inteligencia artificial, Visión por Computadora, AR, Gamificación.\n\n" .
                "*(Cada proyecto es único y se ajusta a tus necesidades).*\n" .
                "¿Cuál se acerca más a lo que buscas?";
        }

        // CONTACTO
        if (preg_match('/(contacto|telefono|whatsapp|email|llamar)/i', $lowerMessage)) {
            return "📞 **Contáctanos directo:**\n\n" .
                "**WhatsApp / Tel:** 2217596585\n" .
                "**Email:** infinexttechnologies@gmail.com\n" .
                "**Horario:** Lun-Vie 8AM-6PM\n\n" .
                "¡Estamos listos para conectar tu negocio con el mundo digital! 🌐";
        }

        // SERVICIOS
        if (preg_match('/(servicio|que hacen|ofrecen)/i', $lowerMessage)) {
            return "🚀 **Servicios Principales:**\n\n" .
                "• **Desarrollo Web y Sistemas** a la medida (Backend escalable)\n" .
                "• **Sistemas Específicos** (E-commerce, POS, E-learning)\n" .
                "• **Visión Artificial e IA** (Detección en tiempo real, automatización)\n" .
                "• **Realidad Aumentada (AR)** y Gamificación 2D/3D\n" .
                "• **Consultoría Tecnológica**\n\n" .
                "¿En qué área te gustaría innovar?";
        }

        // SALUDOS
        if (preg_match('/^(hola|hello|hi|buenos|hey|que tal)/i', $lowerMessage)) {
            return "¡Hola! 👋 Soy Nexi, asistente de **InfiNext**.\n\n" .
                "Puedo ayudarte con:\n\n" .
                "📦 Paquetes y Soluciones Web\n" .
                "🤖 Sistemas e IA\n" .
                "💼 Casos de éxito\n" .
                "📞 Contacto y Asesoría\n\n" .
                "**¿En qué puedo ayudarte hoy?**";
        }

        // DESPEDIDAS
        if (preg_match('/(gracias|bye|adios|chao|nos vemos)/i', $lowerMessage)) {
            return "¡De nada! Fue un placer ayudarte. 😊\n\n" .
                "Para cualquier duda futura:\n" .
                "📞 WhatsApp: 2217596585\n" .
                "📧 infinexttechnologies@gmail.com\n\n" .
                "¡Que tengas un excelente día! 🚀";
        }

        // GENÉRICA
        return "¡Hola! 👋 Soy Nexi, asistente IA de **InfiNext**.\n\n" .
            "Te ayudo con:\n\n" .
            "🏢 Sobre nosotros\n" .
            "📦 Soluciones desde \$4,000\n" .
            "🚀 Integración de IA y Sistemas\n" .
            "💼 Nuestros Proyectos\n" .
            "📋 Cotizaciones a la medida\n\n" .
            "**¿Qué te gustaría saber?**";
    }

    private function removeAccents(string $string): string
    {
        return str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
            $string
        );
    }

    /**
     * ★ CONOCIMIENTO DEL ASISTENTE ★
     * Este es el "cerebro" del chatbot: edita aquí cuando cambien
     * precios, proyectos, servicios o datos de contacto.
     */
    private function getInfinextKnowledge(): string
    {
        return "Eres Nexi, el asistente virtual oficial de InfiNext, una agencia de innovación tecnológica y desarrollo de software con sede en Puebla, México. Tu personalidad es altamente profesional, innovadora, clara y enfocada en resolver problemas reales para los clientes mediante la tecnología.

DATOS DE CONTACTO:
- Ubicación: Puebla, México (atendemos proyectos a nivel nacional e internacional)
- WhatsApp / Teléfono: 2217596585
- Email: infinexttechnologies@gmail.com
- Horario: Lunes a Viernes, 3:00 PM - 9:00 PM
- Tiempo de respuesta: menos de 24 horas hábiles

SOLUCIONES Y PAQUETES BASE (Precios en MXN, los costos finales dependen de los requerimientos):
1. Presencia Digital — Desde \$4,000 (Landing page corporativa, diseño responsivo, SEO básico, enlaces a WhatsApp/Redes).
2. Sistemas y Gestión — Desde \$10,000 (Desarrollo backend a la medida, bases de datos, paneles de administración, control de inventarios/usuarios). ⭐ El más solicitado.
3. Innovación NextGen — Cotización a la medida (Desarrollo con Inteligencia Artificial, Visión por Computadora, Realidad Aumentada, y Gamificación 2D).

SERVICIOS PRINCIPALES:
- Desarrollo Web y Sistemas a la medida (Arquitecturas backend escalables y seguras).
- Sistemas especificos (E-commerce, puntos de venta, plataformas de E-learnign).
- Visión Artificial e IA (Detección de objetos en tiempo real, análisis de datos, automatización inteligente).
- Realidad Aumentada (AR) (Experiencias inmersivas para educación o marketing).
- Videojuegos y Entornos Interactivos (Gamificación para capacitación industrial o entretenimiento).
- Consultoría e Integración Tecnológica.

PROYECTOS DESTACADOS:
1. Gestión de Laboratorios (INAOE) — Software de gestión y semi-automatización para el Laboratorio de Iluminación y Eficiencia Eléctrica.
2. Oasis AR — App móvil educativa con Realidad Aumentada para la enseñanza interactiva de culturas de Oasisamérica.
3. Car Madness — Videojuego 2D de plataformas y quiz diseñado para la capacitación en seguridad industrial y gestión de calidad.
4. Sistemas de Visión Artificial — Modelos de tracking de personas en emergencias y detección de baches usando IA.

TECNOLOGÍAS QUE DOMINAMOS:
- Backend & Web: PHP, CodeIgniter 4, Laravel, Next.js, Inertia, MongoDB, MySQL, JavaScript, HTML5/CSS3.
- Inteligencia Artificial: YOLO, MediaPipe, OpenCV.
- Interactividad y 3D: Unity, C#, Vuforia, Blender.

INSTRUCCIONES DE COMPORTAMIENTO:
- Eres Nexi. Responde SIEMPRE en el idioma en que te escriba el usuario (español por defecto).
- Sé claro, conciso y usa formato markdown con negritas y listas para facilitar la lectura.
- Usa emojis con moderación (1-2 por respuesta para mantener un tono profesional).
- Nunca inventes precios, servicios o proyectos que no estén en esta información.
- Si no sabes algo, admítelo con profesionalismo e invita a contactar por WhatsApp o email.
- Si el usuario pide una cotización o quiere iniciar un proyecto, pídele: nombre, email, teléfono (opcional) y detalles del proyecto.
- Si el cliente busca un sistema de ventas, recomiéndale el paquete 'Sistemas y Gestión'.
- Mantén las respuestas por debajo de 150 palabras para una lectura rápida.";
    }
}
