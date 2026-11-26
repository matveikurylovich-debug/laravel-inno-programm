#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$envelopeSchemaPath = $root.'/docs/events/envelope.schema.json';
$envelopeSchema = json_decode((string) file_get_contents($envelopeSchemaPath), true, 512, JSON_THROW_ON_ERROR);
$requiredEnvelopeKeys = $envelopeSchema['required'] ?? [];

$fixtures = glob($root.'/contracts/*/*.json') ?: [];
if ($fixtures === []) {
    fwrite(STDERR, "No contract fixtures found in contracts/*/*.json\n");
    exit(1);
}

$errors = [];

foreach ($fixtures as $fixturePath) {
    $topic = basename(dirname($fixturePath));
    $payload = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        $errors[] = "{$fixturePath}: root must be a JSON object";

        continue;
    }

    foreach ($requiredEnvelopeKeys as $key) {
        if (! array_key_exists($key, $payload)) {
            $errors[] = "{$fixturePath}: missing required envelope field [{$key}]";
        }
    }

    foreach (array_keys($payload) as $key) {
        if (! in_array($key, $requiredEnvelopeKeys, true)) {
            $errors[] = "{$fixturePath}: unexpected root envelope field [{$key}]";
        }
    }

    if (($payload['schema_version'] ?? null) !== 1) {
        $errors[] = "{$fixturePath}: schema_version must equal 1";
    }

    if (! is_string($payload['event_id'] ?? null) || $payload['event_id'] === '') {
        $errors[] = "{$fixturePath}: event_id must be a non-empty string";
    }

    $eventType = $payload['event_type'] ?? null;
    if (! is_string($eventType) || $eventType === '') {
        $errors[] = "{$fixturePath}: event_type must be a non-empty string";

        continue;
    }

    $data = $payload['data'] ?? null;
    if (! is_array($data)) {
        $errors[] = "{$fixturePath}: data must be a JSON object";

        continue;
    }

    $topicSchemaPath = $root."/docs/events/{$topic}.schema.json";
    if (! is_file($topicSchemaPath)) {
        $errors[] = "{$fixturePath}: missing schema file docs/events/{$topic}.schema.json";

        continue;
    }

    $topicSchema = json_decode((string) file_get_contents($topicSchemaPath), true, 512, JSON_THROW_ON_ERROR);
    $eventSchema = $topicSchema['events'][$eventType] ?? null;
    if (! is_array($eventSchema)) {
        $errors[] = "{$fixturePath}: event_type [{$eventType}] is not declared in docs/events/{$topic}.schema.json";

        continue;
    }

    foreach ($eventSchema['required'] ?? [] as $reqField) {
        if (! array_key_exists($reqField, $data)) {
            $errors[] = "{$fixturePath}: missing required data field [{$reqField}] for [{$eventType}]";
        }
    }

    foreach ($eventSchema['properties'] ?? [] as $prop => $rule) {
        if (! array_key_exists($prop, $data) || $data[$prop] === null) {
            continue;
        }
        $expectedType = $rule['type'] ?? null;
        $value = $data[$prop];
        $ok = match ($expectedType) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value),
            default => true,
        };
        if (! $ok) {
            $errors[] = "{$fixturePath}: data.{$prop} expected type [{$expectedType}], got [".get_debug_type($value).']';
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    exit(1);
}

printf("Verified %d event contract fixtures against envelope and topic schemas.\n", count($fixtures));
