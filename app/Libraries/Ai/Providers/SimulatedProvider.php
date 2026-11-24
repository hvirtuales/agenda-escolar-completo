<?php

namespace App\Libraries\Ai\Providers;

/**
 * Proveedor simulado SOLO para desarrollo sin clave de API: permite probar cupos, reservas, pantallas y flujos.
 * Sus respuestas son fijas y se marcan como «simulada»; nunca se usa en producción.
 */
class SimulatedProvider implements Provider
{
    public function isReal(): bool
    {
        return false;
    }

    private static function tokens(string $s): int
    {
        return (int) ceil(mb_strlen($s) / 4);
    }

    public function complete(array $req): array
    {
        $in   = self::tokens($req['system']) + array_sum(array_map(static fn ($m) => self::tokens((string) $m['content']), $req['messages']));
        $last = (string) (end($req['messages'])['content'] ?? '');
        $text = match (true) {
            ! empty($req['schema'])                    => json_encode($this->questions($req['meta'] ?? []), JSON_UNESCAPED_UNICODE),
            ($req['feature'] ?? '') === 'study'        => 'Respuesta de ejemplo (sin IA real): con la IA configurada, aquí te explicaría esta página con otras palabras y te daría un ejemplo. Mientras tanto, vuelve a leer el objetivo de la página e intenta explicarlo con tus palabras.',
            ($req['feature'] ?? '') === 'feedback'     => 'Propuesta de ejemplo (sin IA real): Hiciste un buen trabajo al organizar tu respuesta y explicar tu procedimiento. Para mejorar, revisa el paso en el que tuviste más dificultad y justifica cada resultado. ¡Vas por buen camino!',
            ($req['feature'] ?? '') === 'copilot'      => 'Borrador de ejemplo (sin IA real): con la IA configurada, aquí verás una propuesta basada en el contenido de esta página para «' . mb_substr($last, 0, 80) . '». Revísala y adáptala antes de usarla.',
            default                                    => $this->tutor($last),
        };

        return ['text' => $text, 'input_tokens' => $in, 'output_tokens' => self::tokens($text) + 300, 'model' => 'simulado', 'simulated' => true];
    }

    private function tutor(string $last): string
    {
        $q = mb_strtolower($last);
        if (str_contains($q, 'respuesta') || str_contains($q, 'resultado')) {
            return 'No te daré la respuesta final, pero vamos paso a paso. ¿Qué datos del enunciado ya tienes claros y cuál es la pregunta exacta que debes responder?';
        }

        return 'Buena pregunta. Antes de seguir: ¿qué parte del reto se relaciona con lo que preguntas? Intenta escribir el primer paso con tus palabras y lo revisamos juntos.';
    }

    private function questions(array $meta): array
    {
        $topic = $meta['topic'] ?: 'el tema';
        $n     = max(1, min(10, (int) ($meta['count'] ?? 3)));
        $types = $meta['types'] ?: ['single'];
        $out   = [];
        for ($i = 0; $i < $n; $i++) {
            $type  = $types[$i % count($types)];
            $base  = ['type' => $type, 'text' => 'Pregunta de ejemplo ' . ($i + 1) . ' sobre ' . $topic . ' (simulada).', 'options' => [], 'correct_indexes' => [],
                'numeric_value' => null, 'tolerance' => null, 'accepted_answers' => [], 'points' => 1, 'topic' => $topic, 'difficulty' => $meta['difficulty'] ?: 'medium'];
            $out[] = match ($type) {
                'single'    => ['options' => ['Opción correcta', 'Distractor 1', 'Distractor 2', 'Distractor 3'], 'correct_indexes' => [0]] + $base,
                'multiple'  => ['options' => ['Correcta A', 'Distractor', 'Correcta B', 'Distractor'], 'correct_indexes' => [0, 2]] + $base,
                'truefalse' => ['options' => ['Verdadero', 'Falso'], 'correct_indexes' => [0]] + $base,
                'numeric'   => ['numeric_value' => 3, 'tolerance' => 0] + $base,
                'short'     => ['accepted_answers' => ['respuesta']] + $base,
                default     => ['points' => 2] + $base,
            };
        }

        return ['questions' => $out];
    }
}
