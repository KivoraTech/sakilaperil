<?php

/*
 * Prints a production .env for cPanel with every secret generated (no SSH / artisan needed):
 *   php scripts/make-env.php > sakila.env
 * Then fill in the <ANGLE BRACKET> values and upload it next to the package (sakila-deploy.sh puts it
 * in place as ~/sakila/.env). The file holds every secret of the site: it is git-ignored (*.env);
 * keep one copy somewhere safe and never commit, email or publish it.
 * Run it ONCE for a new site: re-generating APP_KEY later signs everyone out.
 */
declare(strict_types=1);

$template = __DIR__.'/../.env.production.example';
if (! is_file($template)) {
    fwrite(STDERR, "Missing .env.production.example\n");
    exit(1);
}
$secrets = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'IP_HASH_SALT' => bin2hex(random_bytes(24)),
    'NEWSLETTER_TOKEN_SECRET' => bin2hex(random_bytes(32)),
    'INSTALL_TOKEN' => bin2hex(random_bytes(16)),
];
$out = preg_replace_callback('/^([A-Z0-9_]+)=$/m', fn (array $m): string => isset($secrets[$m[1]]) ? $m[1].'='.$secrets[$m[1]] : $m[0], (string) file_get_contents($template));
echo $out;
fwrite(STDERR, "\nYour one-time install token (for https://<your-domain>/install): {$secrets['INSTALL_TOKEN']}\n");
fwrite(STDERR, "This settings file contains secrets: keep it out of git and out of public_html, and delete the\nINSTALL_TOKEN line from the server's .env after you have created your administrator.\n");
