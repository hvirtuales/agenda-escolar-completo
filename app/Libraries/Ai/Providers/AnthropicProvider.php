<?php

namespace App\Libraries\Ai\Providers;

use App\Libraries\Ai\AiException;

/**
 * Claude (Anthropic) por la API de Messages.
 *
 * Usa HTTP directo (CURLRequest de CodeIgniter) porque el proyecto aún no tiene Composer para instalar el SDK oficial
 * de PHP (anthropic-ai/sdk). Cuando se instale, este adaptador se reemplaza por uno con el SDK sin tocar el gateway.
 *
 * - Pensamiento adaptativo (por defecto en los modelos actuales, también Haiku 5.5); la profundidad se controla con
 *   output_config.effort, que se envía siempre explícito.
 * - Salida estructurada con output_config.format (json_schema) cuando la función la pide.
 * - fallbacks = "default" solo en los modelos que lo aceptan (Config\Ai::$serverFallback): si el clasificador de seguridad
 *   declina, la API reintenta con otro modelo en la misma llamada. Haiku 5.5 no lo tiene: un rechazo llega como «refusal».
 * - stop_reason "refusal" se trata como respuesta no disponible; "max_tokens" en una salida JSON, como incompleta.
 */
class AnthropicProvider implements Provider
{
    public function __construct(private string $apiKey)
    {
    }

    public function isReal(): bool
    {
        return true;
    }

    public function complete(array $req): array
    {
        $cfg  = config('Ai');
        $body = [
            'model'         => $req['model'],
            'max_tokens'    => (int) $req['max_tokens'],
            'system'        => $req['system'],
            'messages'      => $req['messages'],
            'output_config' => ['effort' => $req['effort']],
        ];
        if (! empty($req['schema'])) {
            $body['output_config']['format'] = ['type' => 'json_schema', 'schema' => $req['schema']];
        }
        $headers = ['Content-Type' => 'application/json', 'x-api-key' => $this->apiKey, 'anthropic-version' => $cfg->apiVersion];
        if (in_array($req['model'], $cfg->serverFallback, true)) {
            $body['fallbacks']         = 'default';
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }

        try {
            // Instancia propia (no compartida): Services::curlrequest(options, response, config, getShared).
            $res = \Config\Services::curlrequest(['timeout' => $cfg->timeout, 'http_errors' => false], null, null, false)->post($cfg->endpoint, [
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'IA: sin conexión con el proveedor: ' . $e->getMessage());

            throw new AiException('No hay conexión con el servicio de IA. Intente de nuevo en un momento.');
        }

        $code = $res->getStatusCode();
        $data = json_decode((string) $res->getBody(), true) ?: [];
        if ($code !== 200) {
            log_message('error', 'IA: HTTP ' . $code . ' ' . ($data['error']['type'] ?? '') . ': ' . ($data['error']['message'] ?? ''));

            throw new AiException(match (true) {
                $code === 401, $code === 403 => 'La IA no está bien configurada en la plataforma. Avise al administrador.',
                $code === 429, $code === 529, $code >= 500 => 'El servicio de IA está ocupado. Intente de nuevo en un momento.',
                default => 'La IA no pudo procesar la solicitud.',
            });
        }

        $stop = $data['stop_reason'] ?? '';
        if ($stop === 'refusal') {
            throw new AiException('La IA no puede ayudar con esa solicitud. Reformúlela con relación a la actividad.');
        }
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if (! empty($req['schema']) && $stop === 'max_tokens') {
            throw new AiException('La respuesta quedó incompleta. Pida menos elementos e intente de nuevo.');
        }

        return [
            'text'          => trim($text),
            'input_tokens'  => (int) ($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
            'model'         => (string) ($data['model'] ?? $req['model']),
            'simulated'     => false,
        ];
    }
}
