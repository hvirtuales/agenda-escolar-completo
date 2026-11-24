<?php

namespace App\Libraries\Ai\Providers;

/**
 * Adaptador de proveedor de modelos (documento maestro 5.4, «Model Provider Adapter»).
 *
 * complete() recibe: model, effort, max_tokens, system (texto), messages ([role, content]), schema (JSON Schema
 * opcional para salida estructurada) y feature. Devuelve: text, input_tokens, output_tokens, model, simulated.
 * Ante un fallo lanza AiException con un mensaje para la persona.
 */
interface Provider
{
    public function complete(array $req): array;

    /** ¿Responde de verdad (true) o es el simulador de desarrollo (false)? */
    public function isReal(): bool;
}
