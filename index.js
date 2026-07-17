const express = require('express');
const { Resend } = require('resend');
const resend = new Resend(process.env.RESEND_API_KEY);
const cors = require('cors');
const fetch = require('node-fetch');

const app = express();

// ==========================================
// 1. CONFIGURACIÓN GLOBAL (MIDDLEWARES)
// ==========================================
app.use(cors({
    origin: '*',
    methods: ['POST', 'GET', 'OPTIONS'],
    credentials: true,
    allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With']
}));

app.options('*', cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ==========================================
// 2. PROMPT DEL SISTEMA PARA LA IA
// ==========================================
const systemPrompt = `
Eres Nexi, el asistente virtual EXCLUSIVO y oficial de InfiNext.
Tu misión es convertir visitantes en clientes informados, resolver sus dudas con precisión y motivarlos a iniciar su proyecto o transformación digital.

═══════════════════════════════════════
REGLAS CRÍTICAS DE COMPORTAMIENTO
═══════════════════════════════════════
1. FOCO: Solo hablas de InfiNext y temas relacionados con tecnología, desarrollo de software, inteligencia artificial e interactividad.
2. MOTIVACIÓN: Si piden frases motivadoras, dales una sobre innovación tecnológica, evolución digital o el futuro de los negocios (nuestro concepto "Next").
3. CIERRE: Siempre termina con un CTA (Call to Action). Ejemplo: "¿Te gustaría agendar una asesoría para tu idea?" o "¿Exploramos cómo integrar esto en tu negocio?"
4. RESTRICCIÓN: Si preguntan temas ajenos, di: "Lo siento, como asistente de InfiNext, mi especialidad es la innovación tecnológica y el desarrollo de software. ¿Hablamos de tu próximo proyecto?"
5. FORMATO: Usa emojis apropiados con moderación, **negritas** en precios y datos clave, y saltos de línea para facilitar la lectura. Usa markdown.
6. CIERRE DE VENTA: Si el usuario muestra interés real en cotizar, indícale que puede iniciar proporcionando: nombre, email, teléfono (opcional) y una breve descripción del proyecto.
7. ENFOQUE A SOLUCIONES: Si el cliente tiene un negocio físico o necesita automatizar, recomienda fuertemente el paquete 'Sistemas y Gestión'. Si busca destacar visualmente, orienta la plática hacia 'Innovación NextGen'.
8. HONESTIDAD: No inventes precios, plazos, proyectos ni características fuera de los indicados abajo. Si no sabes algo, invita a que te contacten directamente.

═══════════════════════════════════════
INFORMACIÓN DE LA EMPRESA
═══════════════════════════════════════
- Nombre: InfiNext
- Ubicación: Puebla, México (atendemos proyectos a nivel nacional e internacional)
- WhatsApp / Teléfono: 2217596585
- Email: infinexttechnologies@gmail.com
- Horario: Lunes a Viernes 3:00 PM – 9:00 PM
- Web: infinext.dpdns.org

MISIÓN: Convertir ideas complejas en soluciones digitales fáciles de usar, escalables y orientadas a resultados reales.
VISIÓN: Ser los aliados tecnológicos que lideren la próxima generación de desarrollo e inteligencia artificial en México.
VALORES: Innovación Práctica · Acompañamiento · Evolución Constante

═══════════════════════════════════════
SOLUCIONES Y PAQUETES BASE
(Son puntos de partida. Cada proyecto final se cotiza a la medida)
═══════════════════════════════════════

💻 **Presencia Digital — Desde $4,000 MXN**
El impulso inicial para destacar en internet y captar clientes.
Incluye: Landing Page o sitio corporativo, diseño responsivo, botones de contacto/WhatsApp, optimización SEO básica.

⚙️ **Sistemas y Gestión — Desde $10,000 MXN** ⭐ MÁS SOLICITADO
Digitalización total para puntos de venta, inventarios, plataformas e-learning y administración.
Incluye: Desarrollo backend estructurado, bases de datos, panel administrativo a la medida, control de usuarios/roles, integración de APIs.

🚀 **Innovación NextGen — Cotización a la medida**
Soluciones de alta tecnología para automatizar procesos complejos o destacar en el mercado.
Incluye: Modelos de Inteligencia Artificial y Visión Computacional, Apps con Realidad Aumentada (AR), gamificación y entornos interactivos 2D/3D, automatización avanzada.

═══════════════════════════════════════
SERVICIOS PRINCIPALES
═══════════════════════════════════════
- 🌐 Desarrollo Web y Plataformas a la medida (Arquitecturas robustas, cero plantillas).
- 🏢 Sistemas empresariales (E-commerce, Puntos de Venta, E-learning, Gestión).
- 👁️ Visión Artificial e Inteligencia Artificial (Detección en tiempo real, análisis).
- 🥽 Realidad Aumentada (AR) inmersiva para educación o industrias.
- 🎮 Videojuegos y Experiencias Interactivas (Gamificación para capacitación o marketing).
- ⚙️ Consultoría Tecnológica e Integración de Sistemas.

═══════════════════════════════════════
PROYECTOS DESTACADOS (PORTAFOLIO)
═══════════════════════════════════════
1. **Gestión de Laboratorios (INAOE)** — Software de gestión y semi-automatización desarrollado para el Laboratorio de Iluminación y Eficiencia Eléctrica. Interfaz 3D y control para eficiencia.
   Stack: Unity, C#, Blender.

2. **Oasis AR** — Aplicación móvil educativa interactiva. Utiliza Realidad Aumentada para la enseñanza inmersiva de las culturas de Oasisamérica (suroeste antiguo).
   Stack: Unity, Vuforia, C#, Figma, Blender.

3. **Car Madness** — Videojuego 2D de plataformas y quiz. Diseñado estratégicamente para la capacitación en seguridad industrial y gestión de calidad.
   Stack: Unity, C#.

4. **Sistemas de Visión IA** — Modelos avanzados de tracking (seguimiento) de personas en escenarios de emergencia y sistemas de detección de baches mediante visión artificial.
   Stack: Python, YOLO, MediaPipe, OpenCV.

═══════════════════════════════════════
TECNOLOGÍAS QUE DOMINAMOS
═══════════════════════════════════════
- Backend & Frameworks: PHP, CodeIgniter 4, Laravel, Next.js, Inertia.
- Bases de Datos: MySQL, MongoDB.
- Frontend: HTML5, CSS3, JavaScript.
- Inteligencia Artificial: YOLO, MediaPipe, OpenCV, integración de LLMs.
- Interactividad, 3D y AR: Unity, C#, Vuforia, Blender.

═══════════════════════════════════════
FORMAS DE PAGO Y TIEMPOS
═══════════════════════════════════════
- Transferencia bancaria (SPEI), Depósito, Tarjeta de crédito/débito, PayPal.
- Esquema estándar: 50% de anticipo para iniciar labores y asignación de recursos (no reembolsable), 50% contra entrega y liberación del proyecto.
- Tiempos de entrega: Dependen de la complejidad. Siempre entregamos un cronograma claro al aprobar la propuesta técnica/comercial.

TONO: Profesional, tecnológico, claro, directo y con una actitud de "aliado estratégico" dispuesto a ayudar.
`;

// ==========================================
// 3. RUTA PARA ENVIAR COTIZACIONES POR EMAIL
// ==========================================
app.post('/send-quote', async (req, res) => {
    const { nombre, email, telefono, tipo, detalles } = req.body;

    if (!nombre || !email) {
        return res.status(400).json({ success: false, error: 'Datos incompletos' });
    }

    try {
        const data = await resend.emails.send({
    from: 'InfiNext <onboarding@resend.dev>',
    to: 'infinexttechnologies@gmail.com',
    subject: `Nueva solicitud de ${tipo}: ${nombre}`,
    html: `
    <!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        /* Estilos generales optimizados para clientes de correo (Gmail, Outlook, etc.) */
        body { 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            line-height: 1.6; 
            color: #334155; 
            background-color: #f1f5f9; 
            margin: 0; 
            padding: 20px; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }
        
        /* Header con el degradado Teal de InfiNext */
        .header { 
            background: linear-gradient(135deg, #004c4c 0%, #008080 55%, #01c2c2 100%); 
            color: #ffffff; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header h2 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 700; 
            letter-spacing: 0.5px; 
        }
        .header p { 
            margin: 8px 0 0; 
            font-size: 14px; 
            color: #FED137; /* Acento amarillo */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .content { padding: 35px 30px; }
        
        .greeting { 
            font-size: 16px; 
            margin-bottom: 25px; 
            color: #0f172a; 
            text-align: center;
        }

        /* Tarjeta de datos del cliente */
        .field-grid { 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 25px; 
        }
        .field { margin-bottom: 16px; }
        .field:last-child { margin-bottom: 0; }
        
        .field-label { 
            font-size: 12px; 
            color: #64748b; 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 0.5px; 
            margin-bottom: 4px; 
        }
        .field-value { 
            font-size: 16px; 
            color: #0f172a; 
            font-weight: 500; 
        }
        .field-value a { 
            color: #008080; 
            text-decoration: none; 
            font-weight: 600;
        }

        /* Caja destacada para el mensaje del cliente */
        .project-box { 
            background: #ffffff; 
            border-left: 4px solid #FED137; 
            padding: 15px 20px; 
            margin-top: 8px; 
            border-radius: 0 8px 8px 0; 
            border-top: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            font-style: italic;
            color: #475569;
        }

        /* Botón de Acción */
        .cta-container { text-align: center; margin-top: 35px; }
        .cta-button { 
            display: inline-block; 
            background-color: #008080; 
            color: #ffffff !important; 
            text-decoration: none; 
            padding: 14px 32px; 
            border-radius: 8px; 
            font-weight: bold; 
            font-size: 16px; 
        }

        /* Footer */
        .footer { 
            background: #f8fafc; 
            text-align: center; 
            padding: 20px; 
            color: #64748b; 
            font-size: 12px; 
            border-top: 1px solid #e2e8f0; 
        }
        .footer strong { color: #008080; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Encabezado -->
        <div class="header">
            <h2>🤖 Nuevo Prospecto Capturado</h2>
            <p>Nexi - InfiNext AI</p>
        </div>
        
        <!-- Contenido Principal -->
        <div class="content">
            <div class="greeting">
                ¡Hola! Tienes una nueva solicitud para <strong>${tipo}</strong> gestionada por tu asistente virtual.
            </div>

            <!-- Datos de Contacto -->
            <div class="field-grid">
                <div class="field">
                    <div class="field-label">👤 Nombre del Cliente</div>
                    <div class="field-value">${nombre}</div>
                </div>
                
                <div class="field">
                    <div class="field-label">📧 Correo Electrónico</div>
                    <div class="field-value"><a href="mailto:${email}">${email}</a></div>
                </div>
                
                <div class="field">
                    <div class="field-label">📞 Teléfono / WhatsApp</div>
                    <div class="field-value">${telefono || 'No proporcionado'}</div>
                </div>
            </div>

            <!-- Detalles del Proyecto -->
            <div class="field">
                <div class="field-label">💬 Detalles del Proyecto</div>
                <div class="project-box">
                    "${detalles}"
                </div>
            </div>

            <!-- Botón para responder rápidamente -->
            <div class="cta-container">
                <a href="mailto:${email}?subject=Respuesta a tu solicitud en InfiNext&body=Hola ${nombre},%0D%0A%0D%0AGracias por escribirnos a través de Nexi. He revisado los detalles de tu proyecto y..." class="cta-button">
                    Responder a ${nombre}
                </a>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="footer">
            Este mensaje fue generado automáticamente por <strong>Nexi AI</strong>.<br>
            📅 Fecha de registro: ${new Date().toLocaleString('es-MX')}
        </div>
    </div>
</body>
</html>
    `
});
        res.json({ success: true, message: 'Cotización enviada con Resend', data });
    } catch (error) {
        console.error('Error enviando con Resend:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// ==========================================
// 4. RUTA PRINCIPAL (CHATBOT IA CON GROQ)
// ==========================================
app.post('/', async (req, res) => {
    const { message, history } = req.body;
    const apiKey = process.env.GROQ_API_KEY;

    try {
        let rawHistory = typeof history === 'string' ? JSON.parse(history) : (history || []);
        let parsedHistory = rawHistory.filter(msg => msg.content && msg.content.trim() !== "");

        const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${apiKey}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: "llama-3.3-70b-versatile",
                messages: [
                    { role: "system", content: systemPrompt },
                    ...parsedHistory,
                    { role: "user", content: message }
                ],
                stream: false
            })
        });

        const data = await response.json();

        if (data.choices && data.choices[0]) {
            res.json({ success: true, message: data.choices[0].message.content });
        } else {
            throw new Error("Respuesta inválida de Groq");
        }

    } catch (error) {
        console.error("Error en Render:", error.message);
        res.status(500).json({ success: false, message: error.message });
    }
});

// ==========================================
// RUTA PARA LA TRADUCCION DE LA PAGINA
// ==========================================

app.post('/translate-bridge', async (req, res) => {
    // 1. Validar datos mínimos
    if (!req.body.texts || Object.keys(req.body.texts).length === 0) {
        return res.status(400).json({ error: "Sin textos" });
    }

    try {
        const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${process.env.GROQ_API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                model: "llama-3.1-8b-instant",
                messages: [
                    { role: "system", content: "Responde solo con JSON válido." },
                    { role: "user", content: "Traduce a " + req.body.lang + ": " + JSON.stringify(req.body.texts) }
                ]
            })
        });

        const data = await response.json();
        
        // Si hay error de Groq, responder 429 para que el navegador PARE
        if (data.error) return res.status(429).json({ error: "Límite" });

        const content = data.choices[0].message.content.replace(/```json|```/g, '').trim();
        res.json({ success: true, translations: JSON.parse(content) });
        
    } catch (error) {
        // Enviar error 500 para que el JS sepa que debe detenerse
        res.status(500).json({ error: "Error interno" });
    }
});

// ==========================================
// 5. INICIALIZACIÓN DEL SERVIDOR
// ==========================================
const PORT = process.env.PORT || 10000;
app.listen(PORT, () => console.log(`Servidor Groq Infinext listo en puerto ${PORT}`));
