<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Inteligencia artificial (documento maestro 5.4 y 5.8).
 *
 * - La clave del proveedor se guarda cifrada desde Consola › IA (Libraries/Ai/KeyStore) o va en .env (ANTHROPIC_API_KEY); nunca en el código.
 * - Sin clave: en desarrollo se usa un proveedor simulado (respuestas marcadas «simulada») para probar cupos y
 *   pantallas; en producción la IA aparece como «no configurada» y el resto de la plataforma sigue funcionando.
 * - Modelo fijo: Claude Haiku 5.5 (decisión del propietario de la plataforma, 2026-11-23). Los niveles «rápido» y
 *   «avanzado» son ese mismo modelo con distinto esfuerzo. Haiku 5.5 no tiene respaldo del lado del servidor ante un
 *   rechazo, así que no se le envía `fallbacks` (ver $serverFallback).
 * - Límite por solicitud (nivel 4 de 5.8): longitud máxima de la entrada (caracteres) y de la respuesta (tokens) por
 *   función. Las respuestas incluyen el razonamiento interno del modelo, por eso max_tokens no es la longitud del texto.
 */
class Ai extends BaseConfig
{
    public string $provider   = 'anthropic';
    public string $endpoint   = 'https://api.anthropic.com/v1/messages';
    public string $apiVersion = '2026-10-21';
    public int $timeout       = 90; // segundos

    /**
     * Modelos por nivel: id, esfuerzo y precio en USD por millón de tokens (entrada / salida). Haiku 5.5 cobra más cuando
     * el prompt supera long_from tokens de entrada: in_long / out_long para toda esa llamada.
     */
    public array $models = [
        'fast'     => ['id' => 'claude-haiku-5-5', 'effort' => 'low', 'label' => 'Rápido', 'in' => 0.10, 'out' => 0.50, 'in_long' => 0.50, 'out_long' => 2.50, 'long_from' => 100000],
        'advanced' => ['id' => 'claude-haiku-5-5', 'effort' => 'medium', 'label' => 'Avanzado', 'in' => 0.10, 'out' => 0.50, 'in_long' => 0.50, 'out_long' => 2.50, 'long_from' => 100000],
    ];

    /** Modelos que aceptan `fallbacks: "default"` (respaldo del servidor si el clasificador de seguridad declina). */
    public array $serverFallback = ['claude-fable-5-2', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    /**
     * Funciones de la IA. tier: modelo; max_in: caracteres de la pregunta del usuario; max_out: tokens de respuesta;
     * estimate: tokens que se reservan antes de llamar (se ajusta con el consumo real); history: turnos que recuerda.
     */
    public array $features = [
        'tutor'     => ['label' => 'Tutor del estudiante', 'tier' => 'fast', 'max_in' => 800, 'max_out' => 4000, 'estimate' => 2500, 'history' => 8],
        'helpdesk'  => ['label' => 'Asistente de la interfaz', 'tier' => 'fast', 'max_in' => 600, 'max_out' => 2000, 'estimate' => 2000, 'history' => 6],
        'study'     => ['label' => 'Tutor del cuaderno', 'tier' => 'fast', 'max_in' => 800, 'max_out' => 4000, 'estimate' => 2500, 'history' => 6],
        'feedback'  => ['label' => 'Proponer retroalimentación', 'tier' => 'fast', 'max_in' => 800, 'max_out' => 4000, 'estimate' => 3000, 'history' => 0],
        'copilot'   => ['label' => 'Asistente del cuaderno', 'tier' => 'fast', 'max_in' => 1500, 'max_out' => 4000, 'estimate' => 3500, 'history' => 6],
        'questions' => ['label' => 'Generar preguntas', 'tier' => 'advanced', 'max_in' => 1500, 'max_out' => 16000, 'estimate' => 9000, 'history' => 0],
    ];

    /** Cubeta de cupo de cada plantilla de rol (5.8, nivel «rol»). Administración comparte la de coordinación. */
    public array $buckets = [
        'admin' => 'coordinator', 'coordinator' => 'coordinator', 'teacher' => 'teacher', 'student' => 'student', 'guardian' => null,
    ];

    public array $bucketLabels = ['teacher' => 'Docentes', 'coordinator' => 'Coordinación y administración', 'student' => 'Estudiantes'];

    /** Valores iniciales de Ajustes › IA de una institución. */
    public array $defaults = [
        'share_teacher' => 60, 'share_coordinator' => 10, 'share_student' => 30,
        'daily_teacher' => 50000, 'daily_coordinator' => 80000, 'daily_student' => 10000,
        'enabled' => 1, 'label_ai' => 1, 'alert_80' => 1,
    ];

    /** Tokens promedio de una consulta (entrada + salida, medido en uso real): para mostrar «≈ N consultas» en planes y consumo. */
    public int $tokensPerQuery = 3000;

    /** Tokens aproximados de una consulta del estudiante (para mostrar «te quedan unas N consultas»). */
    public int $studentQueryTokens = 1500;
}
