# Paythor SanalPos Pro Magento 2 Installation Guide

This document contains the installation and configuration guide for the Paythor Magento 2 integration in both Turkish and English.

Bu doküman, Paythor Magento 2 entegrasyonu için Türkçe ve İngilizce kurulum ve yapılandırma rehberini içerir.

---

## Türkçe Kurulum Rehberi

### 1. Modül Özeti

Bu entegrasyon iki Magento 2 modülünden oluşur:

- `Eticsoft_PaythorClient`: Paythor API ile iletişim kuran PHP SDK/client katmanıdır.
- `Paythor_SanalPosPro`: Magento 2 ödeme yöntemi, checkout iframe akışı, admin bağlantı ekranı, webhook/callback ve taksit gösterimi modülüdür.

`Paythor_SanalPosPro`, `Eticsoft_PaythorClient` modülüne bağımlıdır. Bu nedenle iki modül birlikte kurulmalıdır.

Composer paket adları:

- `eticsoft/module-paythorclient`
- `paythor/module-sanalpospro`

Magento modül adları:

- `Eticsoft_PaythorClient`
- `Paythor_SanalPosPro`

### 2. Gereksinimler

- Magento 2.x
- PHP `~8.1.0`, `~8.2.0` veya `~8.3.0`
- Magento modülleri: `Magento_Payment`, `Magento_Checkout`, `Magento_Sales`, `Magento_Quote`, `Magento_Store`, `Magento_Config`, `Magento_Backend`, `Magento_Catalog`, `Magento_Directory`
- PHP eklentileri: cURL, JSON, mbstring ve Magento'nun standart gereksinimleri
- Paythor merchant hesabı
- Paythor hesabı için e-posta, şifre ve OTP doğrulama erişimi
- Public/private API key bilgileri veya admin panelden otomatik Paythor Connect akışı
- Sunucudan `https://live-api.sanalpospro.com` adresine outbound HTTPS erişimi

### 3. Dosya Yerleşimi

Manuel `app/code` kurulumu yapılacaksa modül klasörleri şu şekilde yerleştirilmelidir:

```text
app/code/Eticsoft/PaythorClient
app/code/Paythor/SanalPosPro
```

Bu repodaki yapı da aynı namespace ve modül kayıtlarına göre hazırlanmıştır:

```text
Eticsoft/PaythorClient
Paythor/SanalPosPro
```

Magento projesine aktarırken klasörlerin `app/code` altında aynı vendor/module yapısında olduğundan emin olun.

### 4. Kurulum Seçenekleri

#### Seçenek A: Composer ile Kurulum

Paketler private/public Composer repository üzerinden erişilebilir durumdaysa:

```bash
composer require eticsoft/module-paythorclient paythor/module-sanalpospro
php bin/magento module:enable Eticsoft_PaythorClient Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Production ortamda ek olarak:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

#### Seçenek B: app/code ile Manuel Kurulum

Modülleri Magento kök dizinindeki `app/code` altına kopyalayın:

```bash
mkdir -p app/code/Eticsoft app/code/Paythor
cp -R Eticsoft/PaythorClient app/code/Eticsoft/PaythorClient
cp -R Paythor/SanalPosPro app/code/Paythor/SanalPosPro
```

Ardından Magento komutlarını çalıştırın:

```bash
php bin/magento module:enable Eticsoft_PaythorClient Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Production ortamda:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

Kurulumu doğrulamak için:

```bash
php bin/magento module:status Eticsoft_PaythorClient Paythor_SanalPosPro
```

### 5. Veritabanı ve Magento Setup

`Paythor_SanalPosPro` declarative schema kullanır. `setup:upgrade` sırasında `paythor_transaction_log` tablosu oluşturulur.

Tablo amacı:

- Paythor işlem kayıtlarını tutmak
- Magento order ID ve increment ID ile Paythor transaction ID eşleşmelerini saklamak
- `authorize`, `capture`, `refund`, `void`, `callback`, `webhook` gibi aksiyonları loglamak
- Request/response payload, hata kodu ve hata mesajı saklamak

Önemli tablo:

```text
paythor_transaction_log
```

Önemli alanlar:

- `order_id`
- `increment_id`
- `paythor_transaction_id`
- `payment_method`
- `action`
- `status`
- `amount`
- `currency`
- `request_payload`
- `response_payload`
- `error_code`
- `error_message`
- `created_at`

### 6. Admin Panel Yapılandırması

Ana ödeme ayarları:

```text
Stores > Configuration > Sales > Payment Methods > Paythor SanalPos Pro
```

Paythor hesap bağlantı ekranı:

```text
Stores > Paythor > Connect Account
```

Admin ayar alanları:

- `Enabled`: Ödeme yöntemini aktif/pasif yapar.
- `Title`: Checkout'ta müşteriye görünen ödeme yöntemi başlığıdır.
- `Magento App ID`: Magento Paythor uygulama ID'sidir. Varsayılan değer `105`.
- `API Key`: Paythor public key için görünen alandır.
- `API Secret`: Paythor secret/private key için görünen şifreli alandır.
- `Webhook Secret`: Webhook doğrulaması için ayrılmış şifreli alandır.
- `Sandbox Mode`: Test modu göstergesi olarak kullanılır.
- `Payment Action`: Magento ödeme aksiyonu. Authorize/capture davranışı gateway komutları ile desteklenir.
- `New Order Status`: Yeni siparişin ilk statüsüdür. Varsayılan `pending_payment`.
- `Payment from Applicable Countries`: Tüm ülkeler veya belirli ülkeler.
- `Payment from Specific Countries`: Sadece seçilen ülkeler.
- `Accepted Currencies`: Virgülle ayrılmış ISO 4217 para birimleri. Varsayılan `TRY,USD,EUR`.
- `Sort Order`: Checkout ödeme yöntemi sıralaması.
- `Debug Logging`: `var/log/paythor_sanalpospro.log` dosyasına log yazılmasını etkinleştirir.

### 7. Paythor Connect Akışı

Önerilen yapılandırma yöntemi admin paneldeki Connect Account ekranıdır.

Akış:

1. Admin panelde `Stores > Paythor > Connect Account` ekranını açın.
2. Paythor hesap e-posta ve şifresini girin.
3. Sistem Paythor `signin` akışını başlatır ve geçici token alır.
4. OTP kodu e-posta kanalına gönderilir.
5. OTP ekranında doğrulama kodunu girin.
6. Modül Magento uygulamasını Paythor hesabında kurar veya mevcut kurulumu bulur.
7. Public/private API key bilgileri Magento konfigürasyonuna kaydedilir.
8. Config cache temizlenir ve ödeme yöntemi kullanıma hazır hale gelir.

Connect ekranı ayrıca Paythor CDN tabanlı yönetim uygulamasını kullanır:

```text
https://cdn.paythor.com/1/105/10.0.4/index.js
```

CDN uygulaması Magento tarafındaki internal API endpoint'ine istek gönderir:

```text
/paythor/iapi/index
```

Bu endpoint `iapi_xfvv` güvenlik token'ı ile korunur.

### 8. Manuel API Key Yapılandırması

Connect akışı kullanılamıyorsa public/private key değerleri Magento config'e manuel yazılabilir.

Runtime tarafında kullanılan config path'leri:

```text
payment/paythor_sanalpospro/public_key
payment/paythor_sanalpospro/private_key
payment/paythor_sanalpospro/app_instance_id
payment/paythor_sanalpospro/app_id
```

Örnek:

```bash
php bin/magento config:set payment/paythor_sanalpospro/public_key "PUBLIC_KEY"
php bin/magento config:set payment/paythor_sanalpospro/private_key "PRIVATE_KEY"
php bin/magento config:set payment/paythor_sanalpospro/app_id 105
php bin/magento cache:clean config
```

Not: Admin `system.xml` içinde `api_key` ve `api_secret` alanları görünür; mevcut runtime servisleri ise `public_key` ve `private_key` path'lerini okur. Bu nedenle önerilen yöntem Connect Account akışıdır.

### 9. Checkout Ödeme Akışı

Checkout ödeme yöntemi kodu:

```text
paythor_sanalpospro
```

Frontend akışı:

1. Müşteri checkout'ta Paythor ödeme yöntemini seçer.
2. `Place Order` tıklanınca Magento ödeme bilgileri kaydedilir.
3. Modül `POST /paythor/payment/create` endpoint'ine istek gönderir.
4. Magento order hemen oluşturulmaz; aktif quote korunur.
5. Paythor API'den iframe HTML veya ödeme linki alınır.
6. Müşteriye güvenli Paythor ödeme penceresi/modal gösterilir.
7. Başarılı ödeme sonrası `POST /paythor/payment/confirm` çağrılır veya Paythor callback ile dönüş yapılır.
8. Magento order ödeme onayından sonra oluşturulur.
9. Ödeme onaylanırsa sipariş `processing`/başarılı akışa alınır ve invoice oluşturulabilir.
10. Ödeme reddedilirse sipariş iptal edilir veya müşteri sepete geri yönlendirilir.
11. Belirsiz/pending durumda sipariş `pending_payment` kalır ve webhook sonucu beklenir.

Önemli frontend dosyaları:

```text
Paythor/SanalPosPro/view/frontend/layout/checkout_index_index.xml
Paythor/SanalPosPro/view/frontend/web/js/view/payment/sanalpospro.js
Paythor/SanalPosPro/view/frontend/web/js/view/payment/method-renderer/sanalpospro-method.js
Paythor/SanalPosPro/view/frontend/web/template/payment/sanalpospro.html
Paythor/SanalPosPro/view/frontend/web/css/paythor-checkout.css
```

### 10. Callback ve Webhook URL'leri

Magento frontend route frontName:

```text
paythor
```

Ödeme başlatma:

```text
POST {base_url}/paythor/payment/create
```

Ödeme confirm:

```text
POST {base_url}/paythor/payment/confirm
```

Browser callback:

```text
GET {base_url}/paythor/payment/callback
```

Paythor webhook:

```text
POST {base_url}/paythor/webhook/notify
```

Paythor panelinde webhook URL olarak şunu tanımlayın:

```text
https://www.example.com/paythor/webhook/notify
```

Webhook doğrulaması:

- Header: `X-Paythor-Signature`
- Algoritma: HMAC-SHA256
- Mevcut kodda imza doğrulama secret'ı olarak kayıtlı private key kullanılır.
- Geçersiz imza `401 Invalid signature` döndürür.

### 11. Sipariş Durumu ve Ödeme Yaşam Döngüsü

Modül şu operasyonları destekler:

- `authorize`
- `capture`
- `refund`
- `void`
- `callback`
- `webhook`

Başlıca davranışlar:

- Ödeme başlamadan önce quote aktif kalır.
- Order, ödeme başarı sinyali geldikten sonra oluşturulur.
- Başarılı ödeme durumunda order paid/processing akışına alınır.
- Başarısız ödeme durumunda order failed/cancelled akışına alınır.
- Pending/unknown durumlarda order `pending_payment` kalabilir.
- Webhook, browser callback başarısız olduğunda authoritative fallback olarak çalışır.
- Webhook idempotent tasarlanmıştır; halihazırda finalized order tekrar işlenmez.

### 12. Taksit Gösterimi

Modül ürün detay sayfasında Paythor taksit seçeneklerini gösterebilir.

İlgili layout:

```text
Paythor/SanalPosPro/view/frontend/layout/catalog_product_view.xml
```

İlgili block:

```text
Paythor/SanalPosPro/Block/Product/Installments.php
```

Taksit verisi şu config path altında JSON olarak saklanır:

```text
payment/paythor_sanalpospro/installments
```

Taksit tablarının görünürlüğü:

```text
payment/paythor_sanalpospro/showinstallmentstabs
```

Tema seçimi:

```text
payment/paythor_sanalpospro/paymentpagetheme
```

Desteklenen tema değerleri:

- `modern`
- `classic`

Taksit bilgileri Paythor Connect/CDN yönetim ekranı üzerinden güncellenir. Güncelleme sonrası config ve full page cache temizlenir.

### 13. Loglama ve Debug

Debug logging admin panelden etkinleştirilebilir:

```text
Stores > Configuration > Sales > Payment Methods > Paythor SanalPos Pro > Debug Logging
```

Log dosyası:

```text
var/log/paythor_sanalpospro.log
```

İncelenmesi önerilen diğer Magento logları:

```text
var/log/system.log
var/log/exception.log
```

Production ortamda hassas veri içerebileceği için debug logları sürekli açık bırakmayın.

### 14. Bakım Komutu

Processing durumunda kalan ve Paythor tarafında hala `Initiated` görünen ödemeleri capture etmek için console komutu vardır:

```bash
php bin/magento paythor:capture-initiated
```

Dry-run:

```bash
php bin/magento paythor:capture-initiated --dry-run
```

Tek sipariş için:

```bash
php bin/magento paythor:capture-initiated --order-id=000000030
```

### 15. Cache, Static Content ve Production Notları

Konfigürasyon değişikliklerinden sonra:

```bash
php bin/magento cache:clean config
php bin/magento cache:flush
```

Frontend JS/CSS değişikliklerinden sonra developer modda:

```bash
php bin/magento cache:flush
```

Production modda:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

Tema/language bazlı deploy gerekiyorsa:

```bash
php bin/magento setup:static-content:deploy -f tr_TR en_US
```

### 16. Upgrade

Yeni sürüme geçerken:

```bash
composer update eticsoft/module-paythorclient paythor/module-sanalpospro
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

Manuel kurulumda dosyaları güncel sürümle değiştirip aynı Magento komutlarını çalıştırın.

### 17. Devre Dışı Bırakma ve Kaldırma

Ödeme yöntemini sadece pasifleştirmek için admin panelde `Enabled = No` yapın.

Modülü devre dışı bırakmak için:

```bash
php bin/magento module:disable Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

SDK modülü başka bir modül tarafından kullanılmıyorsa ayrıca devre dışı bırakılabilir:

```bash
php bin/magento module:disable Eticsoft_PaythorClient
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Tam kaldırma yapmadan önce order/payment geçmişi, `paythor_transaction_log` tablosu ve config değerleri için yedek alın.

### 18. Sorun Giderme

Ödeme yöntemi checkout'ta görünmüyor:

- `Enabled = Yes` olduğundan emin olun.
- Paythor Connect akışının tamamlandığını kontrol edin.
- `payment/paythor_sanalpospro/public_key` ve `private_key` değerlerinin dolu olduğundan emin olun.
- Ülke ve para birimi kısıtlarını kontrol edin.
- Cache temizleyin.

API isteği başarısız:

- Sunucudan `https://live-api.sanalpospro.com` adresine erişim olduğunu kontrol edin.
- API key/private key değerlerinin doğru olduğunu kontrol edin.
- `var/log/paythor_sanalpospro.log` dosyasını inceleyin.

Webhook `401 Invalid signature` dönüyor:

- Paythor tarafında gönderilen `X-Paythor-Signature` header'ını kontrol edin.
- Magento tarafında kayıtlı private key'in doğru olduğundan emin olun.
- Webhook payload'ın proxy/CDN tarafından değiştirilmediğini kontrol edin.

Order `pending_payment` durumunda kalıyor:

- Webhook URL'nin Paythor panelinde doğru tanımlandığını kontrol edin.
- `paythor/payment/callback` ve `paythor/webhook/notify` endpoint'lerine public erişim olduğundan emin olun.
- Bakım komutu ile initiated capture durumlarını kontrol edin.

Static asset veya JS değişiklikleri görünmüyor:

- Browser cache, Magento cache ve full page cache temizleyin.
- Production modda static content deploy çalıştırın.

### 19. Mevcut Kod İçin Dikkat Edilecek Noktalar

- `Sandbox Mode` admin ve checkout tarafında test göstergesi olarak bulunur; mevcut API client kodu sabit olarak `https://live-api.sanalpospro.com` kullanır. Gerçek sandbox endpoint kullanılacaksa `PaymentConfig::API_BASE_URL` ve client oluşturma akışı ayrıca doğrulanmalıdır.
- Admin `API Key` / `API Secret` alanları `api_key` / `api_secret` path'lerine bağlıdır; runtime servisleri `public_key` / `private_key` path'lerini okur. Bu nedenle Connect Account akışı tercih edilmelidir.
- `Webhook Secret` alanı admin panelde vardır; mevcut webhook doğrulama kodu imza secret'ı olarak private key kullanır.
- Checkout akışı kart bilgisini Magento'da toplamaz; kart bilgileri Paythor güvenli iframe/modal akışında işlenir.

---

## English Installation Guide

### 1. Module Overview

This integration consists of two Magento 2 modules:

- `Eticsoft_PaythorClient`: PHP SDK/client layer used to communicate with the Paythor API.
- `Paythor_SanalPosPro`: Magento 2 payment method module that provides checkout iframe flow, admin account connection, webhook/callback handling, and product-page installment display.

`Paythor_SanalPosPro` depends on `Eticsoft_PaythorClient`, so both modules must be installed together.

Composer package names:

- `eticsoft/module-paythorclient`
- `paythor/module-sanalpospro`

Magento module names:

- `Eticsoft_PaythorClient`
- `Paythor_SanalPosPro`

### 2. Requirements

- Magento 2.x
- PHP `~8.1.0`, `~8.2.0`, or `~8.3.0`
- Magento modules: `Magento_Payment`, `Magento_Checkout`, `Magento_Sales`, `Magento_Quote`, `Magento_Store`, `Magento_Config`, `Magento_Backend`, `Magento_Catalog`, `Magento_Directory`
- PHP extensions: cURL, JSON, mbstring, and the standard Magento requirements
- Paythor merchant account
- Paythor account email/password and OTP access
- Public/private API keys or access to the automatic Paythor Connect flow
- Outbound HTTPS access from the server to `https://live-api.sanalpospro.com`

### 3. File Placement

For manual `app/code` installation, place the modules as follows:

```text
app/code/Eticsoft/PaythorClient
app/code/Paythor/SanalPosPro
```

The repository uses the same namespace/module structure:

```text
Eticsoft/PaythorClient
Paythor/SanalPosPro
```

When copying into a Magento project, make sure the folders preserve the same vendor/module structure under `app/code`.

### 4. Installation Options

#### Option A: Composer Installation

If the packages are available through a private or public Composer repository:

```bash
composer require eticsoft/module-paythorclient paythor/module-sanalpospro
php bin/magento module:enable Eticsoft_PaythorClient Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

#### Option B: Manual app/code Installation

Copy both modules into `app/code` under the Magento root:

```bash
mkdir -p app/code/Eticsoft app/code/Paythor
cp -R Eticsoft/PaythorClient app/code/Eticsoft/PaythorClient
cp -R Paythor/SanalPosPro app/code/Paythor/SanalPosPro
```

Then run:

```bash
php bin/magento module:enable Eticsoft_PaythorClient Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

Verify the modules:

```bash
php bin/magento module:status Eticsoft_PaythorClient Paythor_SanalPosPro
```

### 5. Database and Magento Setup

`Paythor_SanalPosPro` uses Magento declarative schema. During `setup:upgrade`, Magento creates the `paythor_transaction_log` table.

Table purpose:

- Store Paythor transaction records
- Map Magento order ID/increment ID to Paythor transaction ID
- Log actions such as `authorize`, `capture`, `refund`, `void`, `callback`, and `webhook`
- Store request/response payloads, error codes, and error messages

Important table:

```text
paythor_transaction_log
```

Important columns:

- `order_id`
- `increment_id`
- `paythor_transaction_id`
- `payment_method`
- `action`
- `status`
- `amount`
- `currency`
- `request_payload`
- `response_payload`
- `error_code`
- `error_message`
- `created_at`

### 6. Admin Configuration

Main payment settings:

```text
Stores > Configuration > Sales > Payment Methods > Paythor SanalPos Pro
```

Paythor account connection screen:

```text
Stores > Paythor > Connect Account
```

Admin configuration fields:

- `Enabled`: Enables or disables the payment method.
- `Title`: Payment method title shown to the customer at checkout.
- `Magento App ID`: Paythor Magento application ID. Default is `105`.
- `API Key`: Visible field for the Paythor public key.
- `API Secret`: Encrypted visible field for the Paythor secret/private key.
- `Webhook Secret`: Encrypted field reserved for webhook verification.
- `Sandbox Mode`: Test mode indicator.
- `Payment Action`: Magento payment action. Authorize/capture behavior is supported through gateway commands.
- `New Order Status`: Initial status for new orders. Default is `pending_payment`.
- `Payment from Applicable Countries`: All countries or selected countries only.
- `Payment from Specific Countries`: Country whitelist.
- `Accepted Currencies`: Comma-separated ISO 4217 currency codes. Default is `TRY,USD,EUR`.
- `Sort Order`: Checkout payment method sort order.
- `Debug Logging`: Enables logging to `var/log/paythor_sanalpospro.log`.

### 7. Paythor Connect Flow

The recommended configuration method is the admin Connect Account flow.

Flow:

1. Open `Stores > Paythor > Connect Account`.
2. Enter the Paythor account email and password.
3. The module starts the Paythor `signin` flow and receives a temporary token.
4. An OTP code is sent to the account email.
5. Enter the OTP code on the verification screen.
6. The module installs or discovers the Magento app in the Paythor account.
7. Public/private API keys are saved into Magento configuration.
8. Config cache is cleared and the payment method becomes operational.

The Connect screen also loads the Paythor CDN-based management application:

```text
https://cdn.paythor.com/1/105/10.0.4/index.js
```

The CDN app communicates with Magento through this internal API endpoint:

```text
/paythor/iapi/index
```

This endpoint is protected by the `iapi_xfvv` security token.

### 8. Manual API Key Configuration

If the Connect flow cannot be used, public/private keys can be written manually into Magento config.

Runtime config paths:

```text
payment/paythor_sanalpospro/public_key
payment/paythor_sanalpospro/private_key
payment/paythor_sanalpospro/app_instance_id
payment/paythor_sanalpospro/app_id
```

Example:

```bash
php bin/magento config:set payment/paythor_sanalpospro/public_key "PUBLIC_KEY"
php bin/magento config:set payment/paythor_sanalpospro/private_key "PRIVATE_KEY"
php bin/magento config:set payment/paythor_sanalpospro/app_id 105
php bin/magento cache:clean config
```

Note: The admin `system.xml` exposes `api_key` and `api_secret` fields, while the current runtime services read `public_key` and `private_key`. For that reason, the Connect Account flow is preferred.

### 9. Checkout Payment Flow

Payment method code:

```text
paythor_sanalpospro
```

Frontend flow:

1. The customer selects the Paythor payment method at checkout.
2. When `Place Order` is clicked, Magento payment information is saved.
3. The module sends a request to `POST /paythor/payment/create`.
4. The Magento order is not created immediately; the active quote is preserved.
5. The module receives iframe HTML or a payment link from the Paythor API.
6. The customer sees the secure Paythor payment window/modal.
7. After successful payment, the browser calls `POST /paythor/payment/confirm` or returns through the Paythor callback URL.
8. The Magento order is created only after the payment success signal.
9. If the payment is approved, the order moves into the paid/processing flow and an invoice can be created.
10. If the payment is declined, the order is cancelled or the customer is redirected back to the cart.
11. If the status is unknown/pending, the order may remain in `pending_payment` until the webhook finalizes it.

Important frontend files:

```text
Paythor/SanalPosPro/view/frontend/layout/checkout_index_index.xml
Paythor/SanalPosPro/view/frontend/web/js/view/payment/sanalpospro.js
Paythor/SanalPosPro/view/frontend/web/js/view/payment/method-renderer/sanalpospro-method.js
Paythor/SanalPosPro/view/frontend/web/template/payment/sanalpospro.html
Paythor/SanalPosPro/view/frontend/web/css/paythor-checkout.css
```

### 10. Callback and Webhook URLs

Magento frontend route frontName:

```text
paythor
```

Payment creation:

```text
POST {base_url}/paythor/payment/create
```

Payment confirmation:

```text
POST {base_url}/paythor/payment/confirm
```

Browser callback:

```text
GET {base_url}/paythor/payment/callback
```

Paythor webhook:

```text
POST {base_url}/paythor/webhook/notify
```

Use this URL in the Paythor panel:

```text
https://www.example.com/paythor/webhook/notify
```

Webhook verification:

- Header: `X-Paythor-Signature`
- Algorithm: HMAC-SHA256
- The current code uses the stored private key as the signature secret.
- Invalid signatures return `401 Invalid signature`.

### 11. Order Status and Payment Lifecycle

The module supports these operations:

- `authorize`
- `capture`
- `refund`
- `void`
- `callback`
- `webhook`

Main behavior:

- The quote remains active before payment starts.
- The order is created after a successful payment signal.
- Approved payments move the order into the paid/processing flow.
- Failed payments move the order into the failed/cancelled flow.
- Pending/unknown statuses may leave the order in `pending_payment`.
- The webhook acts as the authoritative fallback if the browser callback fails.
- The webhook is idempotent; already finalized orders are acknowledged and not reprocessed.

### 12. Installment Display

The module can display Paythor installment options on the product detail page.

Relevant layout:

```text
Paythor/SanalPosPro/view/frontend/layout/catalog_product_view.xml
```

Relevant block:

```text
Paythor/SanalPosPro/Block/Product/Installments.php
```

Installment data is stored as JSON under:

```text
payment/paythor_sanalpospro/installments
```

Installment tab visibility:

```text
payment/paythor_sanalpospro/showinstallmentstabs
```

Theme selection:

```text
payment/paythor_sanalpospro/paymentpagetheme
```

Supported theme values:

- `modern`
- `classic`

Installment settings are updated through the Paythor Connect/CDN management screen. The module clears config and full page cache after updates.

### 13. Logging and Debugging

Debug logging can be enabled from:

```text
Stores > Configuration > Sales > Payment Methods > Paythor SanalPos Pro > Debug Logging
```

Log file:

```text
var/log/paythor_sanalpospro.log
```

Other useful Magento logs:

```text
var/log/system.log
var/log/exception.log
```

Do not keep debug logging enabled permanently in production because logs may contain sensitive operational data.

### 14. Maintenance Command

There is a console command for processing orders that are in Magento `processing` state but still appear as `Initiated` on the Paythor side:

```bash
php bin/magento paythor:capture-initiated
```

Dry-run:

```bash
php bin/magento paythor:capture-initiated --dry-run
```

Single order:

```bash
php bin/magento paythor:capture-initiated --order-id=000000030
```

### 15. Cache, Static Content, and Production Notes

After configuration changes:

```bash
php bin/magento cache:clean config
php bin/magento cache:flush
```

After frontend JS/CSS changes in developer mode:

```bash
php bin/magento cache:flush
```

In production mode:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

For specific locales:

```bash
php bin/magento setup:static-content:deploy -f tr_TR en_US
```

### 16. Upgrade

For Composer-based installations:

```bash
composer update eticsoft/module-paythorclient paythor/module-sanalpospro
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

For manual installations, replace the module files with the new version and run the same Magento commands.

### 17. Disable and Uninstall

To only disable the payment method, set `Enabled = No` in the admin configuration.

To disable the payment module:

```bash
php bin/magento module:disable Paythor_SanalPosPro
php bin/magento setup:upgrade
php bin/magento cache:flush
```

If no other module uses the SDK module, it can also be disabled:

```bash
php bin/magento module:disable Eticsoft_PaythorClient
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Before full removal, back up order/payment history, the `paythor_transaction_log` table, and related config values.

### 18. Troubleshooting

Payment method is not visible at checkout:

- Make sure `Enabled = Yes`.
- Make sure the Paythor Connect flow is complete.
- Verify that `payment/paythor_sanalpospro/public_key` and `private_key` are populated.
- Check country and currency restrictions.
- Clear Magento cache.

API requests fail:

- Verify outbound access to `https://live-api.sanalpospro.com`.
- Verify API key/private key values.
- Check `var/log/paythor_sanalpospro.log`.

Webhook returns `401 Invalid signature`:

- Check the `X-Paythor-Signature` header sent by Paythor.
- Make sure the private key stored in Magento is correct.
- Make sure the raw webhook payload is not modified by a proxy or CDN.

Order remains in `pending_payment`:

- Verify the webhook URL in the Paythor panel.
- Make sure `paythor/payment/callback` and `paythor/webhook/notify` are publicly reachable.
- Use the maintenance command to check initiated capture cases.

Static assets or JS changes are not visible:

- Clear browser cache, Magento cache, and full page cache.
- Run static content deployment in production mode.

### 19. Current Code Caveats

- `Sandbox Mode` exists in admin and checkout as a test indicator, but the current API client uses the fixed live endpoint `https://live-api.sanalpospro.com`. If a real sandbox endpoint is required, verify `PaymentConfig::API_BASE_URL` and the client creation flow.
- Admin `API Key` / `API Secret` fields are bound to `api_key` / `api_secret`, while runtime services read `public_key` / `private_key`. Prefer the Connect Account flow.
- `Webhook Secret` exists in admin configuration, but the current webhook verifier uses the private key as the HMAC secret.
- The checkout flow does not collect card data in Magento; card details are processed inside the secure Paythor iframe/modal.
