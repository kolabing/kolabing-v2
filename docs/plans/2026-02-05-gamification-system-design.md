# Gamification System — Design Document

**Date:** 2026-02-05
**Status:** Draft
**Author:** Brainstorming Session

---

## 1. Overview

Kolabing platformuna etkinlik bazli bir gamification sistemi eklenmesi. Attendee (katilimci) kullanicilar etkinliklere katilir, diger katilimcilarla QR tabanli challenge'lar yapar, puan kazanir, odulleri toplar ve leaderboard'da yarisir.

Bu ozellik platformu B2B/B2C'den **B2B2C** modeline donusturur. Yeni bir kullanici tipi olan "attendee" eklenir.

### Temel Deger Onerisi

- **Attendee icin:** Etkinliklerde eglenceli deneyim, oduller, sosyal etkilesim
- **Organizator icin:** Katilimci engagement'i artirma, etkinlik cazibesini yukseltme
- **Platform icin:** Kullanici tabani genisletme, retention artirma

---

## 2. Kararlar Ozeti

| # | Konu | Karar |
|---|------|-------|
| 1 | Giris | Sadece mobil app (Google OAuth) |
| 2 | Check-in | QR code (organizator olusturur) |
| 3 | Challenge akisi | QR okut → liste → sec → yap → karsi taraf onayla → puan |
| 4 | Challenge kaynagi | Hibrit — sistem havuzu + organizator custom |
| 5 | Puan & odul | Dinamik puan (zorluk bazli) + anlik spin-the-wheel |
| 6 | Odul yonetimi | Sadece organizator tanimlar ve finanse eder |
| 7 | Attendee profili | Gamification odakli (puan, rozet, gecmis, siralama) |
| 8 | Leaderboard | Etkinlik bazli + global siralama |
| 9 | Odul claim | QR bazli redeem + odul cuzdani sayfasi |
| 10 | Rozetler | Milestone bazli otomatik (sistem tanimli) |
| 11 | Etkinlik kesfi | Acik kesif — harita bazli yakinindaki etkinlikler |

---

## 3. Yeni Kullanici Tipi: Attendee

### 3.1 Giris ve Kayit
- Google OAuth ile kayit (mevcut sistemle ayni)
- `user_type = 'attendee'` olarak profiles tablosuna eklenir
- Sadece mobil app uzerinden erisim

### 3.2 Profil Yapisi (Oyuncu Karti)
Attendee profili gamification odakli bir "oyuncu karti" olarak tasarlanir:

- **Temel bilgiler:** Google'dan gelen isim, email, avatar
- **Toplam puan:** Tum etkinliklerden biriken genel puan
- **Rozet koleksiyonu:** Kazanilan milestone rozetleri
- **Challenge gecmisi:** Tamamlanan challenge listesi
- **Leaderboard siralamasi:** Global siralama pozisyonu
- **Katildigi etkinlikler:** Gecmis etkinlik listesi

### 3.3 Extended Profile Tablosu
`attendee_profiles` tablosu (1:1 profiles ile):
- `total_points` (integer, default 0)
- `total_challenges_completed` (integer, default 0)
- `total_events_attended` (integer, default 0)
- `global_rank` (integer, nullable)

---

## 4. Etkinlik Kesfi

### 4.1 Harita Bazli Kesif
- Attendee harita uzerinde yakinindaki etkinlikleri gorur
- Etkinlikler pin olarak haritada gosterilir
- Filtre: kategori, tarih, mesafe
- "Katil" butonu ile etkinlige kayit olunur

### 4.2 Etkinlik Detay Sayfasi
- Etkinlik bilgileri (isim, tarih, konum, organizator)
- Challenge listesi onizleme
- Odul havuzu onizleme
- Katilimci sayisi
- "Katil" / "Katildim" durumu

---

## 5. Check-in Sistemi

### 5.1 QR Code Olusturma
- Organizator (business/community) app icinden etkinlik icin QR code olusturur
- Her etkinligin unique QR kodu vardir
- QR kod etkinlik baslangic saatinde aktif olur

### 5.2 Check-in Akisi
```
Attendee etkinlige gelir
    → Organizatorun QR kodunu okuttur
    → Sistem check-in kaydini olusturur
    → Attendee artik challenge yapabilir
    → Etkinlik leaderboard'unda gozukur
```

### 5.3 Check-in Kurallari
- Check-in olmadan challenge yapilamaz
- Bir etkinlige bir kez check-in yapilir
- Check-in zamani kaydedilir

---

## 6. Challenge Sistemi

### 6.1 Challenge Kaynagi (Hibrit Model)

**Sistem Havuzu (Platform Tanimli):**
- Genel challenge'lar her etkinlikte kullanilabilir
- Ornekler:
  - "Birlikte selfie cek" (kolay, 5 puan)
  - "3 farkli kisiyle tenis" (orta, 15 puan)
  - "Etkinlik sahnesinde dans et" (zor, 30 puan)
  - "Organizatore bir soru sor" (kolay, 5 puan)
  - "Yeni biriyle 2 dakika sohbet et" (orta, 15 puan)

**Organizator Custom:**
- Organizator etkinlige ozel challenge'lar yazabilir
- Zorluk seviyesi ve puan degerini belirler
- Etkinlik temasina uygun ozel gorevler

### 6.2 Challenge Zorluk Seviyeleri
| Seviye | Puan | Ornek |
|--------|------|-------|
| Kolay | 5 | Selfie cek, selamlasma |
| Orta | 15 | Grup aktivitesi, sohbet |
| Zor | 30 | Sahne performansi, yarisma |

### 6.3 Challenge Akisi (Peer-to-Peer)
```
1. Kullanici A, Kullanici B'nin QR kodunu okuttuyor
2. Her iki ekranda challenge listesi cikiyor
3. Bir taraf challenge seciyor
4. Secilen kisi challenge'i yapiyor
5. Karsi taraf "evet yapti" / "hayir yapamadi" onayliyor
6. "Yapti" ise → puan kazanilir + spin-the-wheel sansi
```

### 6.4 Challenge Kurallari
- Ayni iki kisi arasinda ayni challenge tekrar yapilamaz
- Bir attendee bir etkinlikte maksimum N challenge yapabilir (organizator belirler)
- Challenge onay suresi: etkinlik bitis saatine kadar

---

## 7. Puan Sistemi

### 7.1 Puan Kazanma
- Challenge tamamlama: zorluk seviyesine gore (5/15/30)
- Check-in bonusu: ilk check-in icin bonus puan (opsiyonel)
- Puanlar hem etkinlik bazli hem global olarak birikir

### 7.2 Puan Kullanim Alanlari
- Etkinlik leaderboard siralamasi
- Global leaderboard siralamasi
- Rozet acma esikleri
- Spin-the-wheel hakkina erisim

---

## 8. Odul Sistemi

### 8.1 Spin-the-Wheel (Anlik Odul)
- Challenge tamamlandiktan sonra spin-the-wheel tetiklenir
- Her spin'de odul kazanma sansi vardir (garanti degildir)
- Odul havuzu organizator tarafindan tanimlenir

### 8.2 Odul Havuzu Yonetimi
Organizator etkinlik olustururken odul havuzunu tanimlar:

```json
{
  "rewards": [
    {"name": "Bedava Kahve", "quantity": 20, "probability": 0.3},
    {"name": "VIP Davet", "quantity": 5, "probability": 0.05},
    {"name": "%20 Indirim", "quantity": 50, "probability": 0.5},
    {"name": "Ozel Rozet", "quantity": 100, "probability": 0.15}
  ]
}
```

- Organizator: odul ismi, adet, olasilik belirler
- Stok bitince o odul artik dusmez
- Organizator odul maliyetini kendisi karsilar

### 8.3 Odul Cuzdani
- Kazanilan oduller "Odul Cuzdani" sayfasinda listelenir
- Her odulun durumu: "Kullanilabilir" / "Kullanildi" / "Suresi Doldu"
- Odul son kullanma tarihi organizator tarafindan belirlenir

### 8.4 Odul Redeem (QR Bazli)
```
Attendee odul cuzdanini acar
    → Kullanmak istedigi odulu secer
    → "Kullan" butonuna basar
    → Ekranda odul QR kodu olusur
    → Organizatorun mekanina gider
    → Organizator QR'i okuttur / onaylar
    → Odul "Kullanildi" olarak isaretlenir
```

---

## 9. Leaderboard Sistemi

### 9.1 Etkinlik Leaderboard
- Her etkinligin kendi siralama tablosu
- Sadece o etkinlikte kazanilan puanlar sayilir
- Etkinlik bitince siralama donar
- Top 3 ozel vurgu ile gosterilir

### 9.2 Global Leaderboard
- Tum etkinliklerden biriken toplam puan
- Surekli guncellenen canli siralama
- Haftalik/aylik filtreleme opsiyonu
- "Senin siralamanin" vurgusu

---

## 10. Rozet Sistemi

### 10.1 Milestone Bazli Otomatik Rozetler
Sistem tanimli rozetler, belirli esiklere ulasilinca otomatik acilir:

| Rozet | Kosul |
|-------|-------|
| Ilk Adim | Ilk check-in |
| Challenge Baslangic | Ilk challenge tamamla |
| Sosyal Kelebek | 10 farkli kisiyle challenge yap |
| Challenge Master | 50 challenge tamamla |
| Etkinlik Gurusu | 10 etkinlige katil |
| Puan Avcisi | 500 toplam puan |
| Efsane | 2000 toplam puan |
| Odul Koleksiyoncusu | 10 odul kazan |
| Sadik Katilimci | 5 ardisik etkinlige katil |

### 10.2 Rozet Ozellikleri
- Her rozetin ismi, ikonu ve aciklamasi vardir
- Kazanildiginda push notification gonderilir
- Profilde rozet koleksiyonu olarak gosterilir
- Kazanilmamis rozetler gri/kilitli olarak gorunur

---

## 11. Veritabani Yapisi (Oneri)

### Yeni Tablolar

```
attendee_profiles
- id (uuid, PK)
- profile_id (uuid, FK → profiles)
- total_points (integer, default 0)
- total_challenges_completed (integer, default 0)
- total_events_attended (integer, default 0)
- global_rank (integer, nullable)
- created_at, updated_at

event_checkins
- id (uuid, PK)
- event_id (uuid, FK → events)
- profile_id (uuid, FK → profiles)
- checked_in_at (timestamp)
- UNIQUE(event_id, profile_id)

challenges
- id (uuid, PK)
- name (string)
- description (text)
- difficulty (enum: easy, medium, hard)
- points (integer)
- is_system (boolean, default false)
- event_id (uuid, nullable, FK → events) — NULL ise sistem challenge'i
- created_at, updated_at

challenge_completions
- id (uuid, PK)
- challenge_id (uuid, FK → challenges)
- event_id (uuid, FK → events)
- challenger_profile_id (uuid, FK → profiles) — challenge'i yapan
- verifier_profile_id (uuid, FK → profiles) — onaylayan
- status (enum: pending, verified, rejected)
- points_earned (integer)
- completed_at (timestamp, nullable)
- created_at, updated_at
- UNIQUE(challenge_id, event_id, challenger_profile_id, verifier_profile_id)

event_rewards
- id (uuid, PK)
- event_id (uuid, FK → events)
- name (string)
- description (text, nullable)
- total_quantity (integer)
- remaining_quantity (integer)
- probability (decimal 0-1)
- expires_at (timestamp, nullable)
- created_at, updated_at

reward_claims
- id (uuid, PK)
- event_reward_id (uuid, FK → event_rewards)
- profile_id (uuid, FK → profiles)
- status (enum: available, redeemed, expired)
- won_at (timestamp)
- redeemed_at (timestamp, nullable)
- created_at, updated_at

badges
- id (uuid, PK)
- name (string)
- description (text)
- icon (string)
- milestone_type (string) — ornek: 'first_checkin', 'challenges_50'
- milestone_value (integer)
- created_at, updated_at

badge_awards
- id (uuid, PK)
- badge_id (uuid, FK → badges)
- profile_id (uuid, FK → profiles)
- awarded_at (timestamp)
- UNIQUE(badge_id, profile_id)

event_leaderboards (materialized/cached)
- id (uuid, PK)
- event_id (uuid, FK → events)
- profile_id (uuid, FK → profiles)
- points (integer)
- rank (integer)
- updated_at
- UNIQUE(event_id, profile_id)
```

### Mevcut Tablolarda Degisiklikler

```
profiles tablosu:
- user_type enum'a 'attendee' eklenir

events tablosu:
- location_lat (decimal, nullable) — harita icin
- location_lng (decimal, nullable) — harita icin
- address (string, nullable)
- max_challenges_per_attendee (integer, default 10)
- is_active (boolean, default false) — check-in acik mi
```

---

## 12. API Endpointleri (Oneri)

### Etkinlik Kesfi
```
GET  /api/v1/events/discover?lat={lat}&lng={lng}&radius={km}&category={cat}
```

### Check-in
```
POST /api/v1/events/{event}/checkin     — QR ile check-in
GET  /api/v1/events/{event}/checkins    — etkinlik katilimci listesi
```

### Challenge
```
GET  /api/v1/events/{event}/challenges          — etkinlik challenge listesi
POST /api/v1/challenges/initiate                 — QR okutup challenge baslatma
POST /api/v1/challenges/{completion}/verify      — karsi taraf onayi
GET  /api/v1/me/challenges                       — benim challenge gecmisim
```

### Odul
```
GET  /api/v1/events/{event}/rewards              — etkinlik odul havuzu
POST /api/v1/rewards/spin                        — spin-the-wheel
GET  /api/v1/me/rewards                          — odul cuzdanim
POST /api/v1/rewards/{claim}/redeem              — odul kullan (QR)
```

### Leaderboard
```
GET  /api/v1/events/{event}/leaderboard          — etkinlik siralama
GET  /api/v1/leaderboard/global                  — global siralama
```

### Rozetler
```
GET  /api/v1/badges                              — tum rozetler
GET  /api/v1/me/badges                           — benim rozetlerim
```

### Attendee Profil
```
GET  /api/v1/me/gamification-stats               — puan, rozet, siralama ozeti
GET  /api/v1/profiles/{id}/game-card             — baskasinin oyuncu karti
```

### Organizator Yonetimi
```
POST /api/v1/events/{event}/challenges           — custom challenge ekle
PUT  /api/v1/events/{event}/challenges/{id}      — challenge guncelle
DELETE /api/v1/events/{event}/challenges/{id}     — challenge sil
POST /api/v1/events/{event}/rewards              — odul havuzuna ekle
PUT  /api/v1/events/{event}/rewards/{id}         — odul guncelle
DELETE /api/v1/events/{event}/rewards/{id}        — odul sil
POST /api/v1/events/{event}/qr                   — check-in QR olustur
POST /api/v1/rewards/{claim}/confirm-redeem      — odul redeem onayla
```

---

## 13. Kullanici Akisi (End-to-End)

```
Attendee kayit (Google OAuth)
    → Haritada yakinindaki etkinlikleri kesfet
    → Etkinlige "katil" de
    → Etkinlik gunu organizatorden QR ile check-in
    → Diger attendee'lerin QR'ini okut
    → Challenge listesinden sec
    → Challenge'i yap, karsi taraf onayla
    → Dinamik puan kazan
    → Spin-the-wheel → anlik odul sansi
    → Odul cuzdanina duser
    → Istedigi zaman QR ile redeem et
    → Profilde puan, rozet, siralama birikiyor
    → Etkinlik leaderboard + global leaderboard
```

---

## 14. Implementasyon Oncelikleri

### Faz 1 — Temel Altyapi
1. Attendee user type + attendee_profiles tablosu
2. Event check-in sistemi (QR olusturma + check-in)
3. Challenge modeli (sistem havuzu + custom)
4. Challenge completion akisi (peer-to-peer onay)
5. Temel puan sistemi

### Faz 2 — Odul ve Leaderboard
6. Odul havuzu yonetimi (organizator)
7. Spin-the-wheel mekanizmasi
8. Odul cuzdani + QR redeem
9. Etkinlik leaderboard
10. Global leaderboard

### Faz 3 — Gamification Zenginlestirme
11. Rozet sistemi (milestone tanimlari + otomatik acma)
12. Etkinlik kesfi (harita bazli)
13. Oyuncu karti profil sayfasi
14. Push notification'lar (rozet, odul, challenge)

---

## 15. Teknik Notlar

- **UserType enum:** `attendee` case'i eklenmeli
- **Profil yapisi:** Mevcut dual-portal pattern'e uygun olarak `attendee_profiles` eklenir
- **QR kodlar:** Unique token bazli, JWT veya UUID kullanilabilir
- **Spin-the-wheel:** Backend'de probability bazli rastgele secim, frontend'de animasyon
- **Leaderboard caching:** Event leaderboard materialized view veya cache ile optimize edilmeli
- **Concurrent challenge:** Race condition onleme icin DB lock kullanilmali (odul stok azaltma vb.)
