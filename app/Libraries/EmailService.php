<?php
namespace App\Libraries;

use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Model\SendSmtpEmailTo;
use Brevo\Client\Model\SendSmtpEmailSender;
use GuzzleHttp\Client;

class EmailService
{
    private $apiInstance;
    private $fromName;
    private $fromEmail;
    private $appName;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', env('BREVO_API_KEY'));
        $this->apiInstance = new TransactionalEmailsApi(new Client(), $config);
        $this->fromName = env('EMAIL_FROM_NAME', 'Smart Lock System');
        $this->fromEmail = env('EMAIL_FROM_EMAIL', 'noreply@smartlock.local');
        $this->appName = env('EMAIL_APP_NAME', 'Smart Lock System');
    }

    public function sendLockAlert($toEmail, $lockName, $action, $userName = null)
    {
        $subject = "Lock Alert: {$lockName} {$action}";
        $htmlContent = $this->getLockAlertTemplate($lockName, $action, $userName);
        
        return $this->sendEmail($toEmail, $subject, $htmlContent);
    }

    public function sendStatusAlert($toEmail, $lockName, $status)
    {
        $subject = "Status Alert: {$lockName} is {$status}";
        $htmlContent = $this->getStatusAlertTemplate($lockName, $status);
        
        return $this->sendEmail($toEmail, $subject, $htmlContent);
    }

    public function sendSecurityAlert($toEmail, $message, $lockName = null)
    {
        $subject = "Security Alert" . ($lockName ? ": {$lockName}" : "");
        $htmlContent = $this->getSecurityAlertTemplate($message, $lockName);
        
        return $this->sendEmail($toEmail, $subject, $htmlContent);
    }

    public function sendAccountCreated($toEmail, $username, $tempPassword = null, $createdBy = null)
    {
        $subject = "Welcome to $this->appName - Account Created";
        $htmlContent = $this->getAccountCreatedTemplate($username, $tempPassword, $createdBy);
        
        return $this->sendEmail($toEmail, $subject, $htmlContent);
    }

    public function sendPasswordReset($toEmail, $username, $resetToken)
    {
        $subject = "$this->appName - Password Reset Request";
        $htmlContent = $this->getPasswordResetTemplate($username, $resetToken);
        
        return $this->sendEmail($toEmail, $subject, $htmlContent);
    }

    private function sendEmail($toEmail, $subject, $htmlContent)
    {
        try {
            $sendSmtpEmail = new SendSmtpEmail();
            $sendSmtpEmail->setSender(new SendSmtpEmailSender([
                'name' => $this->fromName,
                'email' => $this->fromEmail
            ]));
            $sendSmtpEmail->setTo([new SendSmtpEmailTo(['email' => $toEmail])]);
            $sendSmtpEmail->setSubject($subject);
            $sendSmtpEmail->setHtmlContent($htmlContent);

            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);
            
            log_message('info', "Email sent successfully to {$toEmail}: {$subject}");
            return ['success' => true, 'messageId' => $result->getMessageId()];
            
        } catch (\Exception $e) {
            log_message('error', "Email send failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getLockAlertTemplate($lockName, $action, $userName)
    {
        $actionColor = $action === 'unlocked' ? '#10B981' : '#EF4444';
        $actionIcon = $action === 'unlocked' ? '🔓' : '🔒';
        $userInfo = $userName ? "by {$userName}" : "";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Lock Alert</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #1f2937; margin: 0; font-size: 24px;'>🔐 Smart Lock Alert</h1>
                </div>
                
                <div style='background-color: {$actionColor}; color: white; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 20px;'>{$actionIcon} Lock {$action}</h2>
                </div>
                
                <div style='margin-bottom: 20px;'>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Lock:</strong> {$lockName}</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Action:</strong> {$action} {$userInfo}</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <div style='border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 0;'>
                        This is an automated message from your {$this->appName}.<br>
                        If this action was not authorized, please check your system immediately.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getStatusAlertTemplate($lockName, $status)
    {
        $statusColor = $status === 'online' ? '#10B981' : '#EF4444';
        $statusIcon = $status === 'online' ? '🟢' : '🔴';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Status Alert</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #1f2937; margin: 0; font-size: 24px;'>📡 Device Status Alert</h1>
                </div>
                
                <div style='background-color: {$statusColor}; color: white; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 20px;'>{$statusIcon} Device {$status}</h2>
                </div>
                
                <div style='margin-bottom: 20px;'>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Lock:</strong> {$lockName}</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Status:</strong> {$status}</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <div style='border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 0;'>
                        This is an automated status notification from your {$this->appName} App.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getSecurityAlertTemplate($message, $lockName)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Security Alert</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #1f2937; margin: 0; font-size: 24px;'>🚨 Security Alert</h1>
                </div>
                
                <div style='background-color: #DC2626; color: white; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 20px;'>⚠️ Security Event Detected</h2>
                </div>
                
                <div style='margin-bottom: 20px;'>
                    " . ($lockName ? "<p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Lock:</strong> {$lockName}</p>" : "") . "
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Alert:</strong> {$message}</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <div style='border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 0;'>
                        <strong>IMMEDIATE ACTION MAY BE REQUIRED</strong><br>
                        Please review your  {$this->appName} App immediately.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getAccountCreatedTemplate($username, $tempPassword, $createdBy)
    {
        $passwordInfo = $tempPassword ? 
            "<p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Temporary Password:</strong> <code style='background-color: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$tempPassword}</code></p>
            <p style='font-size: 14px; color: #DC2626; margin: 10px 0;'><strong>⚠️ Please change your password after first login</strong></p>" :
            "<p style='font-size: 16px; color: #374151; margin: 10px 0;'>Please contact your administrator to set up your password.</p>";
            
        $creatorInfo = $createdBy ? "<p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Created by:</strong> {$createdBy}</p>" : "";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Account Created</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #1f2937; margin: 0; font-size: 24px;'>🔐 Welcome to {$this->appName}</h1>
                </div>
                
                <div style='background-color: #10B981; color: white; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 20px;'>👋 Account Created Successfully</h2>
                </div>
                
                <div style='margin-bottom: 20px;'>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'>Your {$this->appName} account has been created!</p>
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Username:</strong> {$username}</p>
                    {$passwordInfo}
                    {$creatorInfo}
                    <p style='font-size: 16px; color: #374151; margin: 10px 0;'><strong>Created:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <div style='background-color: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;'>
                    <h3 style='color: #1f2937; margin: 0 0 10px 0; font-size: 16px;'>Getting Started:</h3>
                    <ul style='color: #374151; margin: 0; padding-left: 20px;'>
                        <li>Log in to the {$this->appName} App</li>
                        <li>Update your profile information</li>
                        <li>Review your lock permissions</li>
                        <li>Set up notification preferences</li>
                    </ul>
                </div>
                
                <div style='border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center;'>
                    <p style='color: #6b7280; font-size: 14px; margin: 0;'>
                        Welcome to the  {$this->appName} App!<br>
                        If you have any questions, please contact your system administrator.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
}
