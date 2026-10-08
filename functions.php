<?php
function escapeText($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function validWebUrl($value) {
    if ($value === '') return true;
    $scheme = parse_url($value, PHP_URL_SCHEME);
    return in_array(strtolower((string)$scheme), ['http', 'https'], true)
        && filter_var($value, FILTER_VALIDATE_URL) !== false;
}
function repositoryUrl($repository) {
    if ($repository === '') return '';
    if (validWebUrl($repository) && preg_match('~^https://github\\.com/[^/]+/[^/?#]+/?$~i', $repository)) return $repository;
    if (preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repository)) return 'https://github.com/' . $repository;
    return '';
}
function validateProject($data) {
    $project = [];
    foreach (['title','repository','public_url','description','next_step','started_on'] as $field) {
        $project[$field] = trim((string)($data[$field] ?? ''));
    }
    $errors = [];
    if ($project['title'] === '' || mb_strlen($project['title']) > 160) $errors[] = 'Project title is required (maximum 160 characters).';
    if (mb_strlen($project['repository']) > 255) $errors[] = 'Repository field is too long.';
    if (mb_strlen($project['public_url']) > 500 || !validWebUrl($project['public_url'])) $errors[] = 'Public link must be an http:// or https:// URL.';
    if ($project['started_on'] === '') $project['started_on'] = date('Y-m-d');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $project['started_on']);
    if (!$date || $date->format('Y-m-d') !== $project['started_on']) $errors[] = 'Enter a valid start date.';
    return [$project, $errors];
}
