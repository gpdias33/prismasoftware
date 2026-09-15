<?php
/**
 * Recebe o formulário de contato do site e envia por e-mail.
 * Responde "OK" em caso de sucesso — é o que o js/validate.js espera.
 * Qualquer outro texto é exibido como mensagem de erro no formulário.
 */

// Destino da mensagem (redireciona para prismasoftware33@gmail.com)
$receiving_email_address = 'contato@prismasoftware.com.br';

// Remetente do envelope. Precisa ser um endereço do próprio domínio,
// caso contrário o envio é barrado por SPF/DMARC na maioria dos servidores.
$sender_email_address = 'contato@prismasoftware.com.br';

header('Content-Type: text/plain; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

// Honeypot: se estiver preenchido, é bot. Finge sucesso e descarta.
if (!empty($_POST['website'])) {
    exit('OK');
}

function field($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

// Impede injeção de cabeçalhos via quebras de linha
function clean_header($value)
{
    return trim(str_replace(array("\r", "\n", "%0a", "%0d"), ' ', $value));
}

$name    = clean_header(field('name'));
$email   = clean_header(field('email'));
$subject = clean_header(field('subject'));
$message = field('message');

$errors = array();

if ($name === '') {
    $errors[] = 'Informe o seu nome.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Informe um endereço de e-mail válido.';
}
if ($subject === '') {
    $errors[] = 'Informe o assunto.';
}
if ($message === '') {
    $errors[] = 'Escreva a sua mensagem.';
}
if (mb_strlen($message) > 5000) {
    $errors[] = 'A mensagem é longa demais.';
}

if ($errors) {
    http_response_code(422);
    exit(implode(' ', $errors));
}

$body = "Novo contato pelo site prismasoftware.com.br\n\n"
      . "Nome: {$name}\n"
      . "E-mail: {$email}\n"
      . "Assunto: {$subject}\n"
      . "Enviado em: " . date('d/m/Y H:i:s') . "\n"
      . "IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconhecido') . "\n\n"
      . "Mensagem:\n{$message}\n";

$mail_subject = '=?UTF-8?B?' . base64_encode('[Site] ' . $subject) . '?=';

$headers = array(
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: =?UTF-8?B?' . base64_encode('Prisma Software - Site') . '?= <' . $sender_email_address . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'X-Mailer: PHP/' . phpversion(),
);

if (!function_exists('mail')) {
    error_log('contact.php: a função mail() está desabilitada neste servidor.');
    http_response_code(500);
    exit('Não foi possível enviar a mensagem no momento. Escreva diretamente para contato@prismasoftware.com.br.');
}

$sent = @mail(
    $receiving_email_address,
    $mail_subject,
    $body,
    implode("\r\n", $headers),
    '-f' . $sender_email_address
);

if ($sent) {
    exit('OK');
}

// Registra em error_log (hPanel > Avançado > Logs de erro do PHP) para diagnóstico
$last = error_get_last();
error_log('contact.php: mail() falhou. ' . ($last ? $last['message'] : 'sem detalhes do PHP.'));

http_response_code(500);
exit('Não foi possível enviar a mensagem no momento. Escreva diretamente para contato@prismasoftware.com.br.');
