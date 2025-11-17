<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class TestSmtpConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smtp:test {email? : Email de destino para prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica la configuración y conectividad SMTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 DIAGNÓSTICO DE CONEXIÓN SMTP');
        $this->newLine();

        // ========================================
        // PASO 1: VALIDACIÓN DE CONFIGURACIÓN
        // ========================================
        $this->info('📋 PASO 1: Validación de Configuración');
        $this->line('─────────────────────────────────────');

        $config = Config::get('mail.mailers.smtp');
        
        $host = $config['host'] ?? 'NO CONFIGURADO';
        $port = $config['port'] ?? 'NO CONFIGURADO';
        $username = $config['username'] ?? 'NO CONFIGURADO';
        $password = $config['password'] ?? 'NO CONFIGURADO';
        $encryption = $config['timeout'] ?? 'NO CONFIGURADO';
        $encryptionType = env('MAIL_ENCRYPTION', 'NO CONFIGURADO');

        // Mostrar valores con corchetes para detectar espacios
        $this->line("MAIL_HOST: [{$host}]");
        $this->line("MAIL_PORT: [{$port}]");
        $this->line("MAIL_USERNAME: [{$username}]");
        $this->line("MAIL_PASSWORD: [" . (empty($password) ? 'VACÍO' : str_repeat('*', min(strlen($password), 20))) . "]");
        $this->line("MAIL_ENCRYPTION: [{$encryptionType}]");
        $this->line("MAIL_FROM_ADDRESS: [" . Config::get('mail.from.address') . "]");
        $this->line("MAIL_FROM_NAME: [" . Config::get('mail.from.name') . "]");
        
        $this->newLine();

        // Detectar espacios en blanco
        $hasIssues = false;
        if ($host !== trim($host)) {
            $this->error('⚠️  ADVERTENCIA: MAIL_HOST contiene espacios en blanco!');
            $hasIssues = true;
        }
        if ($username !== trim($username)) {
            $this->error('⚠️  ADVERTENCIA: MAIL_USERNAME contiene espacios en blanco!');
            $hasIssues = true;
        }
        if (empty($host) || $host === 'NO CONFIGURADO') {
            $this->error('❌ ERROR: MAIL_HOST no está configurado');
            $hasIssues = true;
        }
        if (empty($username) || $username === 'NO CONFIGURADO') {
            $this->error('❌ ERROR: MAIL_USERNAME no está configurado');
            $hasIssues = true;
        }
        if (empty($password) || $password === 'NO CONFIGURADO') {
            $this->error('❌ ERROR: MAIL_PASSWORD no está configurado');
            $hasIssues = true;
        }

        if (!$hasIssues) {
            $this->info('✅ Configuración validada correctamente');
        }
        $this->newLine();

        // ========================================
        // PASO 2: PRUEBA DE SOCKET
        // ========================================
        $this->info('🔌 PASO 2: Prueba de Conexión de Socket');
        $this->line('─────────────────────────────────────');

        // Limpiar espacios del host
        $cleanHost = trim($host);
        $cleanPort = (int) $port;

        $this->line("Intentando conectar a: {$cleanHost}:{$cleanPort}");
        
        $errno = 0;
        $errstr = '';
        $timeout = 10;

        // Intentar conexión
        $socket = @fsockopen($cleanHost, $cleanPort, $errno, $errstr, $timeout);

        if ($socket) {
            $this->info('✅ Conexión a Socket EXITOSA');
            fclose($socket);
            $socketSuccess = true;
        } else {
            $this->error('❌ Conexión a Socket FALLIDA');
            $this->error("Error #{$errno}: {$errstr}");
            
            // Sugerencias según el error
            if ($errno === 0 && strpos(strtolower($errstr), 'getaddrinfo') !== false) {
                $this->warn('💡 Sugerencia: El host no se puede resolver. Verifica:');
                $this->line('   - ¿Hay espacios en blanco en MAIL_HOST?');
                $this->line('   - ¿El nombre de dominio es correcto?');
                $this->line('   - ¿Tienes conexión a internet?');
                $this->line('   - Intenta con ping: ping ' . $cleanHost);
            } elseif ($errno === 110) {
                $this->warn('💡 Sugerencia: Timeout de conexión');
                $this->line('   - ¿El puerto está bloqueado por firewall?');
                $this->line('   - ¿El servidor está caído?');
            } elseif ($errno === 111) {
                $this->warn('💡 Sugerencia: Conexión rechazada');
                $this->line('   - ¿El puerto es correcto? (587 para TLS, 465 para SSL)');
                $this->line('   - ¿El servicio SMTP está activo en ese puerto?');
            }
            
            $socketSuccess = false;
        }
        $this->newLine();

        // ========================================
        // PASO 3: PRUEBA DE ENVÍO REAL
        // ========================================
        if ($socketSuccess) {
            $this->info('📧 PASO 3: Prueba de Envío Real');
            $this->line('─────────────────────────────────────');

            $testEmail = $this->argument('email');
            
            if (empty($testEmail)) {
                $testEmail = $this->ask('¿A qué correo quieres enviar el email de prueba?', $username);
            }

            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $this->error('❌ Email inválido. Prueba de envío cancelada.');
                return Command::FAILURE;
            }

            $this->line("Enviando email de prueba a: {$testEmail}");

            try {
                Mail::raw('Este es un correo de prueba del comando smtp:test. Si lo recibes, tu configuración SMTP funciona correctamente.', function ($message) use ($testEmail) {
                    $message->to($testEmail)
                        ->subject('🧪 Prueba de Conexión SMTP - ' . config('app.name'));
                });

                $this->info('✅ Email enviado EXITOSAMENTE');
                $this->line('Revisa tu bandeja de entrada (y spam) en: ' . $testEmail);
            } catch (\Exception $e) {
                $this->error('❌ Error al enviar email:');
                $this->error($e->getMessage());
                
                // Sugerencias según el error
                if (strpos($e->getMessage(), 'authentication') !== false) {
                    $this->warn('💡 Sugerencia: Error de autenticación');
                    $this->line('   - Verifica que MAIL_USERNAME y MAIL_PASSWORD sean correctos');
                    $this->line('   - Si usas Gmail, necesitas una "App Password" no tu contraseña normal');
                    $this->line('   - https://myaccount.google.com/apppasswords');
                } elseif (strpos($e->getMessage(), 'Connection') !== false) {
                    $this->warn('💡 Sugerencia: Problema de conexión');
                    $this->line('   - Verifica MAIL_ENCRYPTION (tls para puerto 587, ssl para puerto 465)');
                }
                
                return Command::FAILURE;
            }
        } else {
            $this->warn('⏭️  PASO 3 omitido (socket falló)');
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('🏁 Diagnóstico completado');
        $this->info('═══════════════════════════════════════');

        return Command::SUCCESS;
    }
}
