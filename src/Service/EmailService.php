<?php

namespace App\Service;

use App\Entity\Order;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class EmailService
{
    private ?TransactionalEmailsApi $apiInstance;
    private string $fromEmail;
    private string $fromName;
    private LoggerInterface $logger;

    public function __construct(
        string $brevoApiKey,
        string $brevoFromEmail,
        string $brevoFromName,
        LoggerInterface $logger
    ) {
        // Vérifier si la clé API est configurée
        if (empty($brevoApiKey) || $brevoApiKey === 'your_brevo_api_key_here') {
            $this->logger = $logger;
            $this->logger->warning('Brevo API key not configured. Emails will not be sent.');
            $this->apiInstance = null;
            $this->fromEmail = $brevoFromEmail;
            $this->fromName = $brevoFromName;
            return;
        }
        
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $brevoApiKey);
        $this->apiInstance = new TransactionalEmailsApi(new Client(), $config);
        $this->fromEmail = $brevoFromEmail;
        $this->fromName = $brevoFromName;
        $this->logger = $logger;
    }

    /**
     * Envoie un email de confirmation de commande
     */
    public function sendOrderConfirmation(Order $order): bool
    {
        try {
            // Vérifier si l'API est configurée
            if ($this->apiInstance === null) {
                $this->logger->warning('Cannot send email: Brevo API not configured', [
                    'orderId' => $order->getId(),
                    'orderRef' => $order->getReference(),
                ]);
                return false;
            }
            
            $user = $order->getUser();
            if (!$user || !$user->getEmail()) {
                $this->logger->error('Cannot send order confirmation: user or email missing', [
                    'orderId' => $order->getId(),
                ]);
                return false;
            }

            // Calculer le total de la commande
            $total = 0;
            $items = [];
            foreach ($order->getItems() as $item) {
                $total += (float)$item->getTotalLine();
                $items[] = [
                    'name' => $item->getProductName(),
                    'quantity' => $item->getQuantity(),
                    'price' => (float)$item->getUnitPrice(),
                    'total' => (float)$item->getTotalLine(),
                ];
            }

            $this->logger->info('Preparing to send order confirmation email', [
                'orderId' => $order->getId(),
                'orderRef' => $order->getReference(),
                'userEmail' => $user->getEmail(),
                'itemsCount' => count($items),
                'total' => $total,
            ]);

            // Préparer l'email
            $sendSmtpEmail = new SendSmtpEmail([
                'subject' => 'Confirmation de votre commande ' . $order->getReference(),
                'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
                'to' => [['email' => $user->getEmail(), 'name' => $user->getFirstName() . ' ' . $user->getLastName()]],
                'htmlContent' => $this->getOrderConfirmationHtml($order, $user, $items, $total),
                'textContent' => $this->getOrderConfirmationText($order, $user, $items, $total),
            ]);

            $this->logger->info('Email prepared, sending via Brevo API...');

            // Envoyer l'email
            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            $this->logger->info('Order confirmation email sent successfully', [
                'orderId' => $order->getId(),
                'orderRef' => $order->getReference(),
                'email' => $user->getEmail(),
                'messageId' => $result->getMessageId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'orderId' => $order->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            error_log("EMAIL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Génère le contenu HTML de l'email de confirmation
     */
    private function getOrderConfirmationHtml(Order $order, $user, array $items, float $total): string
    {
        $itemsHtml = '';
        foreach ($items as $item) {
            // Utiliser &nbsp; pour éviter les retours à la ligne sur Gmail mobile
            $priceFormatted = str_replace(' ', '&nbsp;', number_format($item['price'], 2, ',', ' ')) . '&nbsp;€';
            $totalFormatted = str_replace(' ', '&nbsp;', number_format($item['total'], 2, ',', ' ')) . '&nbsp;€';
            
            $itemsHtml .= sprintf(
                '<tr>
                    <td align="left" style="padding: 10px; border-bottom: 1px solid #e5e7eb; color: #333;">%s</td>
                    <td align="center" style="padding: 10px; border-bottom: 1px solid #e5e7eb; color: #333;">%s</td>
                    <td align="right" style="padding: 10px; border-bottom: 1px solid #e5e7eb; color: #333; white-space: nowrap;">%s</td>
                    <td align="right" style="padding: 10px; border-bottom: 1px solid #e5e7eb; color: #333; font-weight: bold; white-space: nowrap;">%s</td>
                </tr>',
                htmlspecialchars($item['name']),
                $item['quantity'],
                $priceFormatted,
                $totalFormatted
            );
        }

        // Préparer les valeurs à insérer pour éviter les problèmes avec sprintf et les % dans le CSS
        $firstName = htmlspecialchars($user->getFirstName());
        $reference = htmlspecialchars($order->getReference());
        $date = $order->getCreatedAt()->format('d/m/Y à H:i');
        $totalFormatted = str_replace(' ', '&nbsp;', number_format($total, 2, ',', ' ')) . '&nbsp;€';
        $email = htmlspecialchars($user->getEmail());

        // Utiliser concaténation au lieu de sprintf pour éviter les problèmes avec % dans le CSS
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center;">
                            <h1 style="color: #2563eb; margin: 0; font-size: 24px;">InnovShop</h1>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px;">
                            <h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 20px;">Confirmation de commande</h2>
                            
                            <p style="margin: 0 0 15px 0; color: #333;">Bonjour <strong>' . $firstName . '</strong>,</p>
                            
                            <p style="margin: 0 0 20px 0; color: #333;">Nous vous confirmons la réception de votre commande <strong>' . $reference . '</strong>.</p>
                            
                            <!-- Order Info Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0f9ff; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <p style="margin: 0 0 10px 0; color: #333;"><strong>Numéro de commande :</strong> ' . $reference . '</p>
                                        <p style="margin: 0 0 10px 0; color: #333;"><strong>Date :</strong> ' . $date . '</p>
                                        <p style="margin: 0; color: #333;"><strong>Statut :</strong> Confirmée</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3 style="color: #1e293b; margin: 30px 0 15px 0; font-size: 18px;">Détail de votre commande</h3>
                            
                            <!-- Order Items Table -->
                            <table width="100%" cellpadding="8" cellspacing="0" border="0" style="border: 1px solid #e5e7eb; margin: 20px 0;">
                                <thead>
                                    <tr style="background-color: #f1f5f9;">
                                        <th align="left" style="padding: 10px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #1e293b;">Produit</th>
                                        <th align="center" style="padding: 10px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #1e293b; width: 60px;">Qté</th>
                                        <th align="right" style="padding: 10px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #1e293b; white-space: nowrap;">Prix unit.</th>
                                        <th align="right" style="padding: 10px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #1e293b; white-space: nowrap;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $itemsHtml . '
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f8fafc;">
                                        <td colspan="3" align="right" style="padding: 15px; border-top: 2px solid #cbd5e1; font-weight: bold; color: #1e293b;">Total</td>
                                        <td align="right" style="padding: 15px; border-top: 2px solid #cbd5e1; font-weight: bold; font-size: 18px; color: #2563eb; white-space: nowrap;">' . $totalFormatted . '</td>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            <!-- Next Steps Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fef3c7; margin: 30px 0;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <p style="margin: 0 0 10px 0; color: #333;">📦 <strong>Prochaines étapes</strong></p>
                                        <p style="margin: 0; color: #333;">Nous préparons votre commande et vous tiendrons informé(e) de son expédition.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 30px 0 20px 0; color: #333;">Vous pouvez suivre l\'état de votre commande dans votre espace client.</p>
                            
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0;">
                                        <a href="http://localhost:5173/account?tab=orders" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 30px; text-decoration: none; font-weight: bold;">Voir ma commande</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 40px 0 0 0; color: #64748b; font-size: 14px;">
                                Pour toute question concernant votre commande, n\'hésitez pas à nous contacter.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 30px 20px; background-color: #f5f5f5;">
                            <p style="margin: 0 0 10px 0; color: #64748b; font-size: 12px;">© 2025 InnovShop - Tous droits réservés</p>
                            <p style="margin: 0; color: #64748b; font-size: 12px;">Cet email a été envoyé à ' . $email . '</p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Génère le contenu texte de l'email de confirmation
     */
    private function getOrderConfirmationText(Order $order, $user, array $items, float $total): string
    {
        $itemsText = '';
        foreach ($items as $item) {
            $itemsText .= sprintf(
                "- %s x%s : %s\n",
                $item['name'],
                $item['quantity'],
                $item['total']
            );
        }

        return sprintf(
            "InnovShop - Confirmation de commande\n\n" .
            "Bonjour %s,\n\n" .
            "Nous vous confirmons la réception de votre commande %s.\n\n" .
            "Numéro de commande : %s\n" .
            "Date : %s\n" .
            "Statut : Confirmée\n\n" .
            "Détail de votre commande :\n" .
            "%s\n" .
            "Total : %s €\n\n" .
            "Nous préparons votre commande et vous tiendrons informé(e) de son expédition.\n\n" .
            "Vous pouvez suivre l'état de votre commande dans votre espace client :\n" .
            "http://localhost:5173/account?tab=orders\n\n" .
            "Pour toute question, n'hésitez pas à nous contacter.\n\n" .
            "© 2025 InnovShop - Tous droits réservés",
            $user->getFirstName(),
            $order->getReference(),
            $order->getReference(),
            $order->getCreatedAt()->format('d/m/Y à H:i'),
            $itemsText,
            number_format($total, 2, ',', ' ')
        );
    }

    /**
     * Envoie un email de notification d'approbation de compte vendeur
     */
    public function sendSellerApprovalNotification($seller): bool
    {
        try {
            // Vérifier si l'API est configurée
            if ($this->apiInstance === null) {
                $this->logger->warning('Cannot send email: Brevo API not configured', [
                    'sellerId' => $seller->getId(),
                    'shopName' => $seller->getShopName(),
                ]);
                return false;
            }
            
            $user = $seller->getUser();
            if (!$user || !$user->getEmail()) {
                $this->logger->error('Cannot send seller approval: user or email missing', [
                    'sellerId' => $seller->getId(),
                ]);
                return false;
            }

            $this->logger->info('Preparing to send seller approval email', [
                'sellerId' => $seller->getId(),
                'shopName' => $seller->getShopName(),
                'userEmail' => $user->getEmail(),
            ]);

            // Préparer l'email
            $sendSmtpEmail = new SendSmtpEmail([
                'subject' => '🎉 Votre compte vendeur a été approuvé !',
                'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
                'to' => [['email' => $user->getEmail(), 'name' => $user->getFirstName() . ' ' . $user->getLastName()]],
                'htmlContent' => $this->getSellerApprovalHtml($seller, $user),
                'textContent' => $this->getSellerApprovalText($seller, $user),
            ]);

            $this->logger->info('Email prepared, sending via Brevo API...');

            // Envoyer l'email
            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);

            $this->logger->info('Seller approval email sent successfully', [
                'sellerId' => $seller->getId(),
                'shopName' => $seller->getShopName(),
                'email' => $user->getEmail(),
                'messageId' => $result->getMessageId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send seller approval email', [
                'sellerId' => $seller->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Génère le contenu HTML de l'email d'approbation vendeur
     */
    private function getSellerApprovalHtml($seller, $user): string
    {
        $firstName = htmlspecialchars($user->getFirstName());
        $shopName = htmlspecialchars($seller->getShopName());
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Compte vendeur approuvé</title>
            </head>
            <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
                <table role="presentation" style="width: 100%%; border-collapse: collapse; background-color: #f3f4f6;">
                    <tr>
                        <td align="center" style="padding: 40px 20px;">
                            <table role="presentation" style="max-width: 600px; width: 100%%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                <!-- Header -->
                                <tr>
                                    <td style="padding: 40px 40px 20px 40px; text-align: center; background-color: #007bff; border-radius: 8px 8px 0 0;">
                                        <h1 style="margin: 0; font-size: 28px; color: #ffffff;">🎉 Félicitations !</h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px;">
                                        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333;">
                                            Bonjour %s,
                                        </p>
                                        
                                        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333;">
                                            Nous avons le plaisir de vous informer que votre demande pour devenir vendeur sur <strong>InnovShop</strong> a été approuvée !
                                        </p>
                                        
                                        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;">
                                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Nom de votre boutique :</p>
                                            <p style="margin: 0; font-size: 18px; font-weight: bold; color: #007bff;">%s</p>
                                        </div>
                                        
                                        <p style="margin: 20px 0; font-size: 16px; line-height: 1.6; color: #333;">
                                            Vous pouvez dès maintenant vous connecter à votre compte et commencer à gérer vos produits.
                                        </p>
                                        
                                        <div style="text-align: center; margin: 30px 0;">
                                            <a href="http://localhost:5173/auth" style="display: inline-block; padding: 14px 32px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                                                Se connecter
                                            </a>
                                        </div>
                                        
                                        <div style="background-color: #e8f4ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                            <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold; color: #007bff;">
                                                🚀 Prochaines étapes :
                                            </p>
                                            <ul style="margin: 0; padding-left: 20px; color: #333;">
                                                <li style="margin-bottom: 8px;">Ajoutez vos premiers produits</li>
                                                <li style="margin-bottom: 8px;">Configurez vos informations de livraison</li>
                                                <li style="margin-bottom: 8px;">Consultez vos statistiques de vente</li>
                                            </ul>
                                        </div>
                                        
                                        <p style="margin: 20px 0 0 0; font-size: 16px; line-height: 1.6; color: #333;">
                                            Bienvenue dans la communauté des vendeurs InnovShop ! 🎊
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="padding: 30px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                            Pour toute question, contactez-nous à support@innovshop.com
                                        </p>
                                        <p style="margin: 0; font-size: 12px; color: #999;">
                                            © 2025 InnovShop - Tous droits réservés
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>',
            $firstName,
            $shopName
        );
    }

    /**
     * Génère le contenu texte de l'email d'approbation vendeur
     */
    private function getSellerApprovalText($seller, $user): string
    {
        return sprintf(
            "🎉 Félicitations !\n\n" .
            "Bonjour %s,\n\n" .
            "Nous avons le plaisir de vous informer que votre demande pour devenir vendeur sur InnovShop a été approuvée !\n\n" .
            "Nom de votre boutique : %s\n\n" .
            "Vous pouvez dès maintenant vous connecter à votre compte et commencer à gérer vos produits.\n\n" .
            "Connectez-vous ici : http://localhost:5173/auth\n\n" .
            "🚀 Prochaines étapes :\n" .
            "- Ajoutez vos premiers produits\n" .
            "- Configurez vos informations de livraison\n" .
            "- Consultez vos statistiques de vente\n\n" .
            "Bienvenue dans la communauté des vendeurs InnovShop ! 🎊\n\n" .
            "Pour toute question, contactez-nous à support@innovshop.com\n\n" .
            "© 2025 InnovShop - Tous droits réservés",
            $user->getFirstName(),
            $seller->getShopName()
        );
    }
}
