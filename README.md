# Paythor SanalPosPro — Geliştirme Fazları

Bu belge, `Paythor_SanalPosPro` ve `Eticsoft_PaythorClient` modüllerinin 3 aşamalı geliştirme sürecini teknik detaylarıyla özetler.
 
---

## Modül Mimarisi

```
Eticsoft_PaythorClient          Paythor_SanalPosPro
  (API İstemcisi)                 (Ödeme Entegrasyonu)
  ├─ SDK mantığı                  ├─ Gateway API (Faz 2)
  ├─ HTTP istekleri               ├─ Admin Config (Faz 1)
  └─ Paythor API iletişimi        ├─ Checkout UI (Faz 3)
                                  └─ Observer / Handler
```

`Paythor_SanalPosPro` modülü, `Eticsoft_PaythorClient`'a bağımlıdır ve onun SDK/API katmanını kullanır.

---

## Faz 1 — İskelet, Bağımlılıklar ve Admin Yapılandırması

### Amaç
Modül iskeletini kurmak, Magento'ya kayıt etmek, admin panel konfigürasyonunu ve veritabanı şemasını oluşturmak.

### Oluşturulan / Güncellenen Dosyalar

| Dosya | Açıklama |
|---|---|
| `etc/module.xml` | Modül beyanı. `setup_version` kullanılmaz (Declarative Schema). `<sequence>` ile `Eticsoft_PaythorClient`, `Magento_Payment`, `Magento_Sales`, `Magento_Checkout`, `Magento_Store` bağımlılıkları tanımlanır. |
| `etc/adminhtml/system.xml` | Admin panel ayarları: Aktiflik, başlık, ödeme aksiyonu (authorize/capture), test modu, API Key, API Secret (obscure), Webhook Secret (obscure), para birimi, sıralama. |
| `etc/config.xml` | Varsayılan değerler: `model` → `PaythorSanalPosProFacade`, `can_authorize`, `can_capture`, `can_refund`, `can_void`, `active`, `title`, `sort_order`, API alanlarının defaults'ları. |
| `etc/acl.xml` | Erişim Kontrol Listesi: `Paythor_SanalPosPro::config`, `::paythor`, `::connect`, `::transactions` kaynak tanımları. |
| `etc/db_schema.xml` | `paythor_transaction_log` tablosu: `entity_id`, `order_id`, `quote_id`, `paythor_transaction_id`, `paythor_process_id`, `status`, `amount`, `currency`, `request_payload`, `response_payload`, `created_at`, `updated_at`. Foreign key: `sales_order.entity_id`. |
| `etc/db_schema_whitelist.json` | Declarative Schema whitelist dosyası. |

### Teknik Kararlar
- `setup_version` niteliği `.cursorrules`'a uygun olarak kullanılmadı — tüm DB değişiklikleri `db_schema.xml` ile yönetilir.
- `api_secret` ve `webhook_secret` alanları `obscure` tipinde tanımlandı (şifreli depolama).
- Hassas veriler (`api_key`, `api_secret`, `webhook_secret`, `sandbox_mode`) `di.xml`'de `TypePool` ile `environment` olarak işaretlendi → `env.php`'ye yazılır, DB'de saklanmaz.

### Doğrulama
```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
```

---

## Faz 2 — Gateway Backend (Payment Provider Gateway API)

### Amaç
Magento 2 Commerce Payment Provider Gateway mimarisine uygun olarak `authorize`, `capture`, `refund`, `void` işlem akışını kurmak. Mevcut `PaythorAdapter` SDK'sını Gateway API katmanıyla sarmak.

### Mimari Diyagram

```
placeOrder()
  │
  ▼
CommandPool (di.xml)
  ├─ authorize → GatewayCommand
  │    ├─ RequestBuilder (AuthorizationDataBuilder + PaymentDataBuilder)
  │    ├─ TransferFactory
  │    ├─ PaythorGatewayClient → PaythorAdapter.createPaymentFromGateway()
  │    ├─ GeneralResponseValidator
  │    └─ HandlerChain (TransactionIdHandler + PaymentDetailsHandler)
  │
  ├─ capture → GatewayCommand
  │    ├─ CaptureDataBuilder + PaymentDataBuilder
  │    ├─ PaythorGatewayClient → PaythorAdapter.captureFromGateway()
  │    └─ ...
  │
  ├─ refund → GatewayCommand
  │    ├─ RefundDataBuilder + PaymentDataBuilder
  │    ├─ PaythorGatewayClient → PaythorAdapter.refundFromGateway()
  │    └─ ...
  │
  └─ void → GatewayCommand
       ├─ VoidDataBuilder + PaymentDataBuilder
       ├─ PaythorGatewayClient → PaythorAdapter.voidFromGateway()
       └─ ...
```

### Oluşturulan / Güncellenen Dosyalar

| Dosya | Açıklama |
|---|---|
| **Gateway/Http/TransferFactory.php** | `TransferFactoryInterface` implementasyonu. Request verilerini `TransferInterface` nesnesine dönüştürür. |
| **Gateway/Http/Client/PaythorGatewayClient.php** | `ClientInterface` implementasyonu. İşlem tipine (`authorize`, `capture`, `refund`, `void`) göre `PaythorAdapter`'ın ilgili bridge metodunu çağırır. |
| **Gateway/Request/PaymentDataBuilder.php** | Ortak ödeme verileri: `order_id`, `amount`, `currency`, `customer_email`. `BuilderInterface`. |
| **Gateway/Request/AuthorizationDataBuilder.php** | `action: authorize`, `paythor_token` ve kart bilgileri. |
| **Gateway/Request/CaptureDataBuilder.php** | `action: capture`, `transaction_id` (önceki auth'dan). |
| **Gateway/Request/RefundDataBuilder.php** | `action: refund`, `transaction_id`, `refund_amount`. |
| **Gateway/Request/VoidDataBuilder.php** | `action: void`, `transaction_id`. |
| **Gateway/Response/TransactionIdHandler.php** | API yanıtından `transaction_id`'yi parse eder, `Payment->setTransactionId()` ile saklar. |
| **Gateway/Response/PaymentDetailsHandler.php** | API yanıtındaki ek detayları (`status`, `process_id` vb.) `Payment->setAdditionalInformation()` ile kaydeder. |
| **Gateway/Validator/GeneralResponseValidator.php** | `AbstractValidator` genişletmesi. HTTP durum kodu, `success` flag'i ve Paythor-spesifik hata durumlarını kontrol eder. |
| **Observer/DataAssignObserver.php** | `AbstractDataAssignObserver` genişletmesi. Frontend'den gelen `additional_data` → `PaymentInfo.additionalInformation`. İzin verilen anahtarlar: `paythor_token`, `payment_method`. |
| **Model/Ui/ConfigProvider.php** | `ConfigProviderInterface` implementasyonu. `window.checkoutConfig.payment.paythor_sanalpospro` altına `isActive`, `title`, `isSandbox`, `paymentAction` verileri sağlar. |
| **Model/Api/PaythorAdapter.php** | Mevcut adaptöre bridge metodları eklendi: `createPaymentFromGateway()`, `captureFromGateway()`, `refundFromGateway()`, `voidFromGateway()`, `normalizeApiResponse()`. |
| **etc/di.xml** | Tam Gateway Facade yapılandırması: `PaythorSanalPosProFacade` (VirtualType → `Magento\Payment\Model\Method\Adapter`), `CommandPool`, `ValueHandlerPool`, `ValidatorPool`, `CountryValidator`, `BuilderComposite`'ler, Handler zincirleri, Logger DI. Hassas konfigürasyon `TypePool`. |
| **etc/events.xml** | `payment_method_assign_data_paythor_sanalpospro` → `DataAssignObserver` kaydı. |
| **etc/frontend/di.xml** | `ConfigProvider` → `Magento\Checkout\Model\CompositeConfigProvider` kaydı. |
| **etc/config.xml** | `model` değeri `PaythorSanalPosProFacade` VirtualType'a güncellendi. |

### Teknik Kararlar
- Mevcut `PaythorAdapter` SDK mantığını bozmadan, Gateway API katmanı **bridge pattern** ile sarıldı. `PaythorGatewayClient` doğrudan HTTP çağrısı yapmaz; işlemi adapter'a devreder.
- `ObjectManager` hiçbir yerde kullanılmadı — tüm bağımlılıklar Constructor Injection ile DI üzerinden enjekte edildi.
- `GeneralResponseValidator` hem genel HTTP hatalarını hem Paythor API'ye özgü hata kodlarını ele alır.
- Handler sınıfları sade tutuldu: sadece veri atama yapar, business logic içermez (`.cursorrules` kuralı).

### Doğrulama
```bash
bin/magento setup:di:compile   # DI konfigürasyon doğrulaması
bin/magento setup:upgrade      # Modül ve DB şema doğrulaması
```

---

## Faz 3 — Frontend Checkout (Knockout.js / UI Components)

### Amaç
Checkout sayfasında ödeme metodunun Knockout.js UI Component olarak render edilmesini, Gateway API ile entegre `getData()` akışını, model tabanlı güvenli ödeme penceresini ve modern CSS stillerini tamamlamak.

### Ödeme Akışı (UX)

```
1. Müşteri "Place Order" tıklar
   │
2. setPaymentInformationAction() → additional_data backend'e gider
   │   ├─ paythor_token (varsa)
   │   └─ payment_method: 'credit_card'
   │
3. AJAX POST → paythor/payment/create
   │   → Backend CreateController iframe HTML döner
   │
4. Modal açılır → Paythor iframe gösterilir
   │   → Müşteri kart bilgilerini Paythor'un güvenli formunda girer
   │
5a. postMessage (Paythor iframe → parent window)
   │   ├─ Başarılı: _sendConfirmRequest() → sipariş oluşturulur → redirect
   │   └─ Başarısız: modal kapanır, hata mesajı, sepet korunur
   │
5b. Callback bridge (Paythor redirect → Callback.php → postMessage)
       └─ Aynı akış
```

### Oluşturulan / Güncellenen Dosyalar

| Dosya | Açıklama |
|---|---|
| **view/frontend/web/js/view/payment/sanalpospro.js** | Renderer-list kaydı. `paythor_sanalpospro` kodunu `sanalpospro-method.js` bileşenine eşler. (Değişiklik yok — mevcut haliyle doğru.) |
| **view/frontend/web/js/view/payment/method-renderer/sanalpospro-method.js** | **Güncellendi.** `getData()` → `additional_data: { paythor_token, payment_method }` ile `DataAssignObserver` uyumlu hale getirildi. `window.checkoutConfig.payment.paythor_sanalpospro` üzerinden `ConfigProvider` verileri okunuyor. `isActive()`, `isSandbox()` metodları eklendi. `setPaymentInformationAction()`'a tam `getData()` objesi gönderiliyor. |
| **view/frontend/web/template/payment/sanalpospro.html** | **Güncellendi.** Sandbox modunda otomatik **TEST** badge gösterimi (`isSandbox()`). Kart ikonları doğru namespace ile referanslanıyor. Güvenlik bilgilendirme metni eklendi. |
| **view/frontend/web/css/paythor-checkout.css** | **Yeni.** Modern modal stili (gradient header, rounded corners, shadow), sandbox badge, bilgilendirme kutusu, güvenlik notu, responsive (mobil tam ekran modal). |
| **view/frontend/requirejs-config.js** | **Yeni.** RequireJS modül eşleştirme konfigürasyonu. |
| **view/frontend/layout/checkout_index_index.xml** | **Güncellendi.** `<head>` bloğuna `Paythor_SanalPosPro::css/paythor-checkout.css` referansı eklendi. |

### Frontend → Backend Veri Akışı

```
sanalpospro-method.js
  getData() → { method: 'paythor_sanalpospro', additional_data: { paythor_token, payment_method } }
    │
    ▼
Magento setPaymentInformationAction()
    │
    ▼
events.xml → DataAssignObserver.execute()
    │  PaymentInfo.setAdditionalInformation('paythor_token', ...)
    │  PaymentInfo.setAdditionalInformation('payment_method', ...)
    │
    ▼
Gateway CommandPool → Request Builders → PaythorGatewayClient → PaythorAdapter
```

### ConfigProvider → Frontend Eşleşme

| ConfigProvider (PHP) | JS Erişim | Kullanım |
|---|---|---|
| `isActive` | `config.isActive` | `isActive()` metodu |
| `title` | `config.title` | `getTitle()` (Magento default) |
| `isSandbox` | `config.isSandbox` | `isSandbox()` → TEST badge |
| `paymentAction` | `config.paymentAction` | İleride ödeme akışı branching |

### Teknik Kararlar
- Ödeme formu iframe/redirect tabanlı olduğu için (kart bilgileri Magento'da toplanmıyor) `Magento_Payment/js/view/payment/cc-form` yerine `Magento_Checkout/js/view/payment/default` extend edildi. `.cursorrules`'da `cc-form` önerilse de, bu mimari için `default` doğru seçimdir.
- `redirectAfterPlaceOrder: false` → Magento'nun varsayılan yönlendirmesi devre dışı; akış tamamen custom AJAX ile yönetilir.
- Modal kapandığında (`closed` callback) sepet korunur — müşteri tekrar deneyebilir.
- PostMessage listener iki kaynağı dinler: (A) Paythor iframe'den doğrudan, (B) Callback.php bridge'den.

### Doğrulama
```bash
bin/magento setup:di:compile
bin/magento setup:upgrade --keep-generated
bin/magento cache:flush
```

---

## Tüm Modül Dosya Haritası

```
src/app/code/Paythor/SanalPosPro/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── config.xml
│   ├── di.xml
│   ├── events.xml
│   ├── acl.xml
│   ├── db_schema.xml
│   ├── db_schema_whitelist.json
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       └── di.xml
├── Gateway/
│   ├── Http/
│   │   ├── TransferFactory.php
│   │   └── Client/
│   │       └── PaythorGatewayClient.php
│   ├── Request/
│   │   ├── PaymentDataBuilder.php
│   │   ├── AuthorizationDataBuilder.php
│   │   ├── CaptureDataBuilder.php
│   │   ├── RefundDataBuilder.php
│   │   └── VoidDataBuilder.php
│   ├── Response/
│   │   ├── TransactionIdHandler.php
│   │   └── PaymentDetailsHandler.php
│   └── Validator/
│       └── GeneralResponseValidator.php
├── Model/
│   ├── Api/
│   │   └── PaythorAdapter.php          ← bridge metodları eklendi
│   ├── Config/
│   │   └── PaymentConfig.php
│   └── Ui/
│       └── ConfigProvider.php
├── Observer/
│   └── DataAssignObserver.php
└── view/
    └── frontend/
        ├── layout/
        │   └── checkout_index_index.xml
        ├── requirejs-config.js
        └── web/
            ├── css/
            │   └── paythor-checkout.css
            ├── images/
            │   └── cards/  (PNG ikonlar)
            ├── js/
            │   └── view/payment/
            │       ├── sanalpospro.js
            │       └── method-renderer/
            │           └── sanalpospro-method.js
            └── template/payment/
                └── sanalpospro.html
```

---

## Uygulanan Magento 2 Best Practices

| Kural | Uygulanma Şekli |
|---|---|
| ObjectManager kullanımı yasak | Tüm sınıflar Constructor Injection ile DI kullanır |
| Declarative Schema | `db_schema.xml` + whitelist; `setup_version` yok |
| Gateway API | `Magento\Payment\Model\Method\Adapter` VirtualType Facade |
| Hassas veri koruması | `TypePool` → `environment` (env.php'ye yazılır) |
| Observer'da business logic yasak | Observer sadece veri atar, mantık adapter'da |
| Handler'da sadelik | Sadece Payment objesini günceller |
| Raw SQL yasak | Repository pattern ve Collection kullanımı |
| Around plugin yerine after/before | Plugin kullanılmadı; DI preference ve observer tercih edildi |
| Core kod müdahalesi yasak | Tamamen ayrı modül; preference/plugin yok |
