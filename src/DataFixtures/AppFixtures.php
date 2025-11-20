<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\BudgetGoal;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ==================== USERS ====================
        $users = [];
        
        $admin = new User();
        $admin->setEmail('admin@innovshop.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        // Un admin doit avoir ROLE_ADMIN ET ROLE_USER
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setFirstName('Admin');
        $admin->setLastName('InnovShop');
        $admin->setIsActive(true);
        $manager->persist($admin);
        $users[] = $admin;

        $user1 = new User();
        $user1->setEmail('jean.dupont@email.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
        $user1->setRoles(['ROLE_USER']);
        $user1->setFirstName('Jean');
        $user1->setLastName('Dupont');
        $user1->setIsActive(true);
        $manager->persist($user1);
        $users[] = $user1;

        $user2 = new User();
        $user2->setEmail('marie.martin@email.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
        $user2->setRoles(['ROLE_USER']);
        $user2->setFirstName('Marie');
        $user2->setLastName('Martin');
        $user2->setIsActive(true);
        $manager->persist($user2);
        $users[] = $user2;

        // ==================== ADDRESSES ====================
        $address1 = new Address();
        $address1->setUser($user1);
        $address1->setLabel('Domicile');
        $address1->setFirstName('Jean');
        $address1->setLastName('Dupont');
        $address1->setStreet('15 Rue de la République');
        $address1->setZipCode('75001');
        $address1->setCity('Paris');
        $address1->setCountry('France');
        $address1->setPhone('0612345678');
        $address1->setIsDefault(true);
        $manager->persist($address1);

        $address2 = new Address();
        $address2->setUser($user2);
        $address2->setLabel('Domicile');
        $address2->setFirstName('Marie');
        $address2->setLastName('Martin');
        $address2->setStreet('42 Avenue des Champs-Élysées');
        $address2->setZipCode('75008');
        $address2->setCity('Paris');
        $address2->setCountry('France');
        $address2->setPhone('0698765432');
        $address2->setIsDefault(true);
        $manager->persist($address2);

        // ==================== CATEGORIES ====================
        $catElectronique = new Category();
        $catElectronique->setName('Électronique');
        $catElectronique->setSlug('electronique');
        $catElectronique->setDescription('Tous les appareils électroniques et high-tech');
        $manager->persist($catElectronique);

        $catSmartphones = new Category();
        $catSmartphones->setName('Smartphones');
        $catSmartphones->setSlug('smartphones');
        $catSmartphones->setDescription('Les derniers smartphones du marché');
        $catSmartphones->setParent($catElectronique);
        $manager->persist($catSmartphones);

        $catOrdinateurs = new Category();
        $catOrdinateurs->setName('Ordinateurs');
        $catOrdinateurs->setSlug('ordinateurs');
        $catOrdinateurs->setDescription('PC portables et de bureau');
        $catOrdinateurs->setParent($catElectronique);
        $manager->persist($catOrdinateurs);

        $catVetements = new Category();
        $catVetements->setName('Vêtements');
        $catVetements->setSlug('vetements');
        $catVetements->setDescription('Mode homme et femme');
        $manager->persist($catVetements);

        $catHomme = new Category();
        $catHomme->setName('Homme');
        $catHomme->setSlug('homme');
        $catHomme->setParent($catVetements);
        $manager->persist($catHomme);

        $catFemme = new Category();
        $catFemme->setName('Femme');
        $catFemme->setSlug('femme');
        $catFemme->setParent($catVetements);
        $manager->persist($catFemme);

        $catMaison = new Category();
        $catMaison->setName('Maison & Déco');
        $catMaison->setSlug('maison-deco');
        $catMaison->setDescription('Tout pour la maison');
        $manager->persist($catMaison);

        // ==================== PRODUCTS ====================
        $products = [];

        // Smartphones
        $iphone15 = new Product();
        $iphone15->setCategory($catSmartphones);
        $iphone15->setName('iPhone 15 Pro');
        $iphone15->setSlug('iphone-15-pro');
        $iphone15->setShortDescription('Le smartphone le plus avancé d\'Apple');
        $iphone15->setDescription('iPhone 15 Pro avec puce A17 Pro, appareil photo 48MP, écran Super Retina XDR de 6,1 pouces et châssis en titane.');
        $iphone15->setPrice('1229.00');
        $iphone15->setColors(['Titane Naturel']);
        $iphone15->setSizes(['128GB']);
        $iphone15->setIsFeatured(true);
        $iphone15->setIsPublished(true);
        $manager->persist($iphone15);
        $products[] = $iphone15;

        $samsung = new Product();
        $samsung->setCategory($catSmartphones);
        $samsung->setName('Samsung Galaxy S24 Ultra');
        $samsung->setSlug('samsung-galaxy-s24-ultra');
        $samsung->setShortDescription('Le flagship Samsung avec S Pen');
        $samsung->setDescription('Samsung Galaxy S24 Ultra avec écran Dynamic AMOLED 2X de 6,8", Snapdragon 8 Gen 3, caméra 200MP et S Pen intégré.');
        $samsung->setPrice('1459.00');
        $samsung->setColors(['Titanium Black']);
        $samsung->setSizes(['256GB']);
        $samsung->setIsFeatured(true);
        $samsung->setIsPublished(true);
        $manager->persist($samsung);
        $products[] = $samsung;

        $pixel = new Product();
        $pixel->setCategory($catSmartphones);
        $pixel->setName('Google Pixel 8 Pro');
        $pixel->setSlug('google-pixel-8-pro');
        $pixel->setShortDescription('L\'IA au service de la photographie');
        $pixel->setDescription('Google Pixel 8 Pro avec Tensor G3, écran LTPO OLED 6,7", Magic Eraser et fonctionnalités IA avancées.');
        $pixel->setPrice('1099.00');
        $pixel->setColors(['Obsidian']);
        $pixel->setSizes(['128GB']);
        $pixel->setIsFeatured(false);
        $pixel->setIsPublished(true);
        $manager->persist($pixel);
        $products[] = $pixel;

        // Ordinateurs
        $macbook = new Product();
        $macbook->setCategory($catOrdinateurs);
        $macbook->setName('MacBook Pro 14" M3');
        $macbook->setSlug('macbook-pro-14-m3');
        $macbook->setShortDescription('Performances professionnelles avec la puce M3');
        $macbook->setDescription('MacBook Pro 14 pouces avec puce M3, écran Liquid Retina XDR, 8 Go de RAM et SSD 512 Go. Parfait pour les créatifs.');
        $macbook->setPrice('1999.00');
        $macbook->setColors(['Gris Sidéral']);
        $macbook->setSizes(['512GB']);
        $macbook->setIsFeatured(true);
        $macbook->setIsPublished(true);
        $manager->persist($macbook);
        $products[] = $macbook;

        $dell = new Product();
        $dell->setCategory($catOrdinateurs);
        $dell->setName('Dell XPS 15');
        $dell->setSlug('dell-xps-15');
        $dell->setShortDescription('PC portable premium pour professionnels');
        $dell->setDescription('Dell XPS 15 avec Intel Core i7 13ème génération, écran OLED 15,6" 4K, NVIDIA RTX 4050, 16 Go RAM et SSD 1 To.');
        $dell->setPrice('1899.00');
        $dell->setColors(['Platinum Silver']);
        $dell->setSizes(['1TB']);
        $dell->setIsFeatured(false);
        $dell->setIsPublished(true);
        $manager->persist($dell);
        $products[] = $dell;

        // Vêtements Homme
        $tshirt = new Product();
        $tshirt->setCategory($catHomme);
        $tshirt->setName('T-shirt Premium Coton Bio');
        $tshirt->setSlug('tshirt-premium-coton-bio');
        $tshirt->setShortDescription('Confort et durabilité');
        $tshirt->setDescription('T-shirt en coton bio 100%, coupe moderne, idéal pour le quotidien. Certifié GOTS et Oeko-Tex.');
        $tshirt->setPrice('29.99');
        $tshirt->setColors(['Noir']);
        $tshirt->setSizes(['M']);
        $tshirt->setIsFeatured(false);
        $tshirt->setIsPublished(true);
        $manager->persist($tshirt);
        $products[] = $tshirt;

        $jean = new Product();
        $jean->setCategory($catHomme);
        $jean->setName('Jean Slim Stretch');
        $jean->setSlug('jean-slim-stretch');
        $jean->setShortDescription('Le jean parfait pour toutes les occasions');
        $jean->setDescription('Jean slim avec élasthanne pour un confort optimal. Denim premium avec lavage stone. Coupe ajustée moderne.');
        $jean->setPrice('79.99');
        $jean->setColors(['Bleu Brut']);
        $jean->setSizes(['32']);
        $jean->setIsFeatured(false);
        $jean->setIsPublished(true);
        $manager->persist($jean);
        $products[] = $jean;

        // Vêtements Femme
        $robe = new Product();
        $robe->setCategory($catFemme);
        $robe->setName('Robe Fleurie Été');
        $robe->setSlug('robe-fleurie-ete');
        $robe->setShortDescription('Élégance et fraîcheur pour l\'été');
        $robe->setDescription('Robe longue à motifs floraux, tissu léger et respirant. Parfaite pour les journées ensoleillées. Coupe évasée flatteuse.');
        $robe->setPrice('59.99');
        $robe->setColors(['Multicolore']);
        $robe->setSizes(['M']);
        $robe->setIsFeatured(true);
        $robe->setIsPublished(true);
        $manager->persist($robe);
        $products[] = $robe;

        $blazer = new Product();
        $blazer->setCategory($catFemme);
        $blazer->setName('Blazer Chic Femme');
        $blazer->setSlug('blazer-chic-femme');
        $blazer->setShortDescription('Professionnalisme et style');
        $blazer->setDescription('Blazer cintré en laine mélangée, doublure en satin. Parfait pour le bureau ou les occasions formelles.');
        $blazer->setPrice('129.99');
        $blazer->setColors(['Beige']);
        $blazer->setSizes(['38']);
        $blazer->setIsFeatured(false);
        $blazer->setIsPublished(true);
        $manager->persist($blazer);
        $products[] = $blazer;

        // Maison
        $canape = new Product();
        $canape->setCategory($catMaison);
        $canape->setName('Canapé d\'angle Scandinave');
        $canape->setSlug('canape-angle-scandinave');
        $canape->setShortDescription('Design nordique et confort optimal');
        $canape->setDescription('Canapé d\'angle 5 places en tissu premium, pieds en chêne massif. Design scandinave intemporel avec coussins déhoussables.');
        $canape->setPrice('899.00');
        $canape->setColors(['Gris Clair']);
        $canape->setSizes(['280x200cm']);
        $canape->setIsFeatured(true);
        $canape->setIsPublished(true);
        $manager->persist($canape);
        $products[] = $canape;

        $lampe = new Product();
        $lampe->setCategory($catMaison);
        $lampe->setName('Lampe LED Design');
        $lampe->setSlug('lampe-led-design');
        $lampe->setShortDescription('Éclairage moderne et économique');
        $lampe->setDescription('Lampe de bureau LED avec bras articulé, température de couleur réglable, fonction mémoire et port USB de charge.');
        $lampe->setPrice('49.99');
        $lampe->setColors(['Blanc']);
        $lampe->setIsFeatured(false);
        $lampe->setIsPublished(true);
        $manager->persist($lampe);
        $products[] = $lampe;

        // ==================== PRODUCT IMAGES ====================
        $imageIphone = new ProductImage();
        $imageIphone->setProduct($iphone15);
        $imageIphone->setFilePath('/uploads/products/iphone-15-pro.jpg');
        $imageIphone->setAltText('iPhone 15 Pro Titane Naturel');
        $imageIphone->setPosition(1);
        $manager->persist($imageIphone);

        $imageSamsung = new ProductImage();
        $imageSamsung->setProduct($samsung);
        $imageSamsung->setFilePath('/uploads/products/galaxy-s24-ultra.jpg');
        $imageSamsung->setAltText('Samsung Galaxy S24 Ultra');
        $imageSamsung->setPosition(1);
        $manager->persist($imageSamsung);

        $imageMacbook = new ProductImage();
        $imageMacbook->setProduct($macbook);
        $imageMacbook->setFilePath('/uploads/products/macbook-pro-m3.jpg');
        $imageMacbook->setAltText('MacBook Pro 14 pouces M3');
        $imageMacbook->setPosition(1);
        $manager->persist($imageMacbook);

        $imageRobe = new ProductImage();
        $imageRobe->setProduct($robe);
        $imageRobe->setFilePath('/uploads/products/robe-fleurie.jpg');
        $imageRobe->setAltText('Robe fleurie été');
        $imageRobe->setPosition(1);
        $manager->persist($imageRobe);

        // ==================== CARTS ====================
        $cart1 = new Cart();
        $cart1->setUser($user1);
        $manager->persist($cart1);

        $cartItem1 = new CartItem();
        $cartItem1->setCart($cart1);
        $cartItem1->setProduct($iphone15);
        $cartItem1->setUnitPrice('1229.00');
        $cartItem1->setQuantity(1);
        $manager->persist($cartItem1);

        $cartItem2 = new CartItem();
        $cartItem2->setCart($cart1);
        $cartItem2->setProduct($tshirt);
        $cartItem2->setUnitPrice('29.99');
        $cartItem2->setQuantity(2);
        $manager->persist($cartItem2);

        // ==================== ORDERS ====================
        $order1 = new Order();
        $order1->setUser($user2);
        $order1->setReference('ORD-' . date('Y') . '-00001');
        $order1->setStatus('delivered');
        $order1->setTotalAmount('2058.99');
        $order1->setDeliveryFirstName('Marie');
        $order1->setDeliveryLastName('Martin');
        $order1->setDeliveryStreet('42 Avenue des Champs-Élysées');
        $order1->setDeliveryZipCode('75008');
        $order1->setDeliveryCity('Paris');
        $order1->setDeliveryCountry('France');
        $order1->setDeliveryPhone('0698765432');
        $manager->persist($order1);

        $orderItem1 = new OrderItem();
        $orderItem1->setOrder($order1);
        $orderItem1->setProduct($macbook);
        $orderItem1->setProductName('MacBook Pro 14" M3');
        $orderItem1->setUnitPrice('1999.00');
        $orderItem1->setQuantity(1);
        $orderItem1->setTotalLine('1999.00');
        $manager->persist($orderItem1);

        $orderItem2 = new OrderItem();
        $orderItem2->setOrder($order1);
        $orderItem2->setProduct($robe);
        $orderItem2->setProductName('Robe Fleurie Été');
        $orderItem2->setUnitPrice('59.99');
        $orderItem2->setQuantity(1);
        $orderItem2->setTotalLine('59.99');
        $manager->persist($orderItem2);

        // ==================== BUDGET GOALS ====================
        $goal1 = new BudgetGoal();
        $goal1->setUser($user1);
        $goal1->setLabel('Économies vacances été');
        $goal1->setGoalType('saving');
        $goal1->setTargetAmount('2000.00');
        $goal1->setCurrentAmount('850.00');
        $goal1->setStartDate(new \DateTime('2025-01-01'));
        $goal1->setEndDate(new \DateTime('2025-07-01'));
        $manager->persist($goal1);

        $goal2 = new BudgetGoal();
        $goal2->setUser($user2);
        $goal2->setLabel('Budget shopping mensuel');
        $goal2->setGoalType('spending');
        $goal2->setTargetAmount('300.00');
        $goal2->setCurrentAmount('125.50');
        $goal2->setStartDate(new \DateTime('2025-11-01'));
        $goal2->setEndDate(new \DateTime('2025-11-30'));
        $manager->persist($goal2);

        $manager->flush();
    }
}


