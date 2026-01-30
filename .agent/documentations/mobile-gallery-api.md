# Mobile Implementation Guide: Profile Gallery API

## Genel Bakis

Business ve community kullanicilar profillerine galeri seklinde fotograf yukleyebilir. Her profil en fazla **10 adet** galeri fotosu yukleyebilir. Fotograflar profil sayfasinda grid/galeri gorunumunde listelenir.

Her iki kullanici tipi (business ve community) bu ozelligi kullanabilir.

---

## Akis Diagrami

```
Kullanici profil sayfasinda "Fotograf Ekle" butonuna tiklar
    |
    v
Kamera / Galeri secim modal acilir
    |
    v
Fotograf secilir + opsiyonel caption girilir
    |
    v
POST /api/v1/me/gallery (multipart/form-data)
    |
    +-- Fotograf sayisi < 10  --> 201 Created (basarili)
    |
    +-- Fotograf sayisi >= 10 --> 422 (limit asimi)
    |
    +-- Gecersiz dosya         --> 422 (validation hatasi)
```

---

## Endpoints

| Method | Endpoint | Aciklama |
|--------|----------|----------|
| `GET` | `/api/v1/me/gallery` | Kendi galeri fotograflarini listele |
| `POST` | `/api/v1/me/gallery` | Fotograf yukle |
| `DELETE` | `/api/v1/me/gallery/{photo_id}` | Fotograf sil |
| `GET` | `/api/v1/profiles/{profile_id}/gallery` | Baska bir profilin galerisini gor |

Tum endpoint'ler `Authorization: Bearer {token}` gerektirir.

---

## 1. Galeri Fotograflarini Listele

Kullanicinin kendi galeri fotograflarini getirir.

### Request

```
GET /api/v1/me/gallery
```

### Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": "019c0fe2-1234-7abc-9def-567890abcdef",
      "url": "https://storage.example.com/gallery/019c0fe2.../20260130_abc123.jpg",
      "caption": "Etkinlik fotografimiz",
      "sort_order": 0,
      "created_at": "2026-01-30T17:00:00+00:00"
    },
    {
      "id": "019c0fe2-5678-7abc-9def-567890abcdef",
      "url": "https://storage.example.com/gallery/019c0fe2.../20260130_def456.png",
      "caption": null,
      "sort_order": 1,
      "created_at": "2026-01-30T17:05:00+00:00"
    }
  ]
}
```

### Response Alanlari

| Alan | Tip | Aciklama |
|------|-----|----------|
| `id` | string (UUID) | Fotograf ID |
| `url` | string | Fotografin tam URL'i |
| `caption` | string \| null | Fotograf aciklamasi |
| `sort_order` | number | Siralama (0'dan baslar) |
| `created_at` | string (ISO 8601) | Yuklenme tarihi |

---

## 2. Fotograf Yukle

Galeriye yeni fotograf ekler. `multipart/form-data` olarak gonderilmelidir.

### Request

```
POST /api/v1/me/gallery
Content-Type: multipart/form-data
```

### Request Body

| Alan | Tip | Zorunlu | Validation | Aciklama |
|------|-----|---------|------------|----------|
| `photo` | file | Evet | image, mimes: jpeg/jpg/png/gif/webp, max: 5MB | Yuklenecek fotograf |
| `caption` | string | Hayir | max: 500 karakter | Opsiyonel fotograf aciklamasi |

### Response (201 Created)

```json
{
  "success": true,
  "message": "Photo uploaded successfully.",
  "data": {
    "id": "019c0fe2-9999-7abc-9def-567890abcdef",
    "url": "https://storage.example.com/gallery/019c0fe2.../20260130_xyz789.jpg",
    "caption": "Yeni etkinlik fotografi",
    "sort_order": 2,
    "created_at": "2026-01-30T17:10:00+00:00"
  }
}
```

### Hata Durumlari

| HTTP Kodu | Durum | Response |
|-----------|-------|----------|
| 422 | Fotograf yok / gecersiz format | `{"success": false, "message": "...", "errors": {"photo": [...]}}` |
| 422 | 10 foto limitine ulasildi | `{"success": false, "message": "You can upload a maximum of 10 gallery photos."}` |

---

## 3. Fotograf Sil

Kullanicinin kendi galeri fotografini siler.

### Request

```
DELETE /api/v1/me/gallery/{photo_id}
```

### Response (200 OK)

```json
{
  "success": true,
  "message": "Photo deleted successfully."
}
```

### Hata Durumlari

| HTTP Kodu | Durum | Response |
|-----------|-------|----------|
| 403 | Baskasinin fotografini silmeye calisma | `{"success": false, "message": "You are not authorized to delete this photo."}` |
| 404 | Fotograf bulunamadi | `{"message": "No query results for model..."}` |

---

## 4. Baska Profilin Galerisini Gor

Herhangi bir profilin galeri fotograflarini getirir. Opportunity detay sayfasindan veya profil sayfasindan erisilebilir.

### Request

```
GET /api/v1/profiles/{profile_id}/gallery
```

### Response (200 OK)

Ayni format ile `GET /api/v1/me/gallery` endpointi.

---

## React Native / TypeScript Implementasyonu

### Tipler

```typescript
interface GalleryPhoto {
  id: string;
  url: string;
  caption: string | null;
  sort_order: number;
  created_at: string;
}

interface GalleryListResponse {
  success: boolean;
  data: GalleryPhoto[];
}

interface GalleryUploadResponse {
  success: boolean;
  message: string;
  data: GalleryPhoto;
}
```

### API Servisi

```typescript
// gallery.service.ts
import { apiClient } from './api-client';

export const galleryService = {
  // Kendi galerini getir
  async getMyGallery(): Promise<GalleryPhoto[]> {
    const response = await apiClient.get<GalleryListResponse>('/me/gallery');
    return response.data.data;
  },

  // Baska profilin galerisini getir
  async getProfileGallery(profileId: string): Promise<GalleryPhoto[]> {
    const response = await apiClient.get<GalleryListResponse>(
      `/profiles/${profileId}/gallery`
    );
    return response.data.data;
  },

  // Fotograf yukle
  async uploadPhoto(
    photoUri: string,
    caption?: string
  ): Promise<GalleryPhoto> {
    const formData = new FormData();

    formData.append('photo', {
      uri: photoUri,
      type: 'image/jpeg',
      name: 'gallery_photo.jpg',
    } as any);

    if (caption) {
      formData.append('caption', caption);
    }

    const response = await apiClient.post<GalleryUploadResponse>(
      '/me/gallery',
      formData,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
      }
    );
    return response.data.data;
  },

  // Fotograf sil
  async deletePhoto(photoId: string): Promise<void> {
    await apiClient.delete(`/me/gallery/${photoId}`);
  },
};
```

### Galeri Componenti

```tsx
// GalleryGrid.tsx
import React, { useState, useEffect } from 'react';
import {
  View,
  Image,
  FlatList,
  TouchableOpacity,
  Alert,
  StyleSheet,
  Dimensions,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { galleryService, GalleryPhoto } from '../services/gallery.service';

const COLUMN_COUNT = 3;
const SCREEN_WIDTH = Dimensions.get('window').width;
const PHOTO_SIZE = (SCREEN_WIDTH - 32 - (COLUMN_COUNT - 1) * 4) / COLUMN_COUNT;
const MAX_PHOTOS = 10;

interface GalleryGridProps {
  profileId?: string;     // baska profil icin
  isOwnProfile?: boolean; // kendi profili mi
}

export const GalleryGrid: React.FC<GalleryGridProps> = ({
  profileId,
  isOwnProfile = false,
}) => {
  const [photos, setPhotos] = useState<GalleryPhoto[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    loadPhotos();
  }, [profileId]);

  const loadPhotos = async () => {
    try {
      setLoading(true);
      const data = profileId
        ? await galleryService.getProfileGallery(profileId)
        : await galleryService.getMyGallery();
      setPhotos(data);
    } catch (error) {
      console.error('Gallery load error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleAddPhoto = async () => {
    if (photos.length >= MAX_PHOTOS) {
      Alert.alert('Limit', 'En fazla 10 fotograf yukleyebilirsiniz.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.8,
      allowsEditing: true,
    });

    if (result.canceled) return;

    try {
      setUploading(true);
      const photo = await galleryService.uploadPhoto(
        result.assets[0].uri
      );
      setPhotos((prev) => [...prev, photo]);
    } catch (error: any) {
      if (error.response?.status === 422) {
        Alert.alert('Hata', error.response.data.message);
      }
    } finally {
      setUploading(false);
    }
  };

  const handleDeletePhoto = (photoId: string) => {
    Alert.alert(
      'Fotografi Sil',
      'Bu fotografi silmek istediginizden emin misiniz?',
      [
        { text: 'Iptal', style: 'cancel' },
        {
          text: 'Sil',
          style: 'destructive',
          onPress: async () => {
            try {
              await galleryService.deletePhoto(photoId);
              setPhotos((prev) => prev.filter((p) => p.id !== photoId));
            } catch (error) {
              Alert.alert('Hata', 'Fotograf silinemedi.');
            }
          },
        },
      ]
    );
  };

  const renderItem = ({ item }: { item: GalleryPhoto }) => (
    <TouchableOpacity
      style={styles.photoContainer}
      onLongPress={
        isOwnProfile ? () => handleDeletePhoto(item.id) : undefined
      }
    >
      <Image source={{ uri: item.url }} style={styles.photo} />
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <FlatList
        data={photos}
        renderItem={renderItem}
        keyExtractor={(item) => item.id}
        numColumns={COLUMN_COUNT}
        columnWrapperStyle={styles.row}
        ListFooterComponent={
          isOwnProfile && photos.length < MAX_PHOTOS ? (
            <TouchableOpacity
              style={styles.addButton}
              onPress={handleAddPhoto}
              disabled={uploading}
            >
              {/* + ikonu */}
            </TouchableOpacity>
          ) : null
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: { padding: 16 },
  row: { gap: 4 },
  photoContainer: {
    width: PHOTO_SIZE,
    height: PHOTO_SIZE,
    borderRadius: 8,
    overflow: 'hidden',
    marginBottom: 4,
  },
  photo: { width: '100%', height: '100%' },
  addButton: {
    width: PHOTO_SIZE,
    height: PHOTO_SIZE,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#ccc',
    borderStyle: 'dashed',
    justifyContent: 'center',
    alignItems: 'center',
  },
});
```

---

## Swift / iOS Implementasyonu

### Model

```swift
struct GalleryPhoto: Codable, Identifiable {
    let id: String
    let url: String
    let caption: String?
    let sortOrder: Int
    let createdAt: String

    enum CodingKeys: String, CodingKey {
        case id, url, caption
        case sortOrder = "sort_order"
        case createdAt = "created_at"
    }
}

struct GalleryListResponse: Codable {
    let success: Bool
    let data: [GalleryPhoto]
}

struct GalleryUploadResponse: Codable {
    let success: Bool
    let message: String
    let data: GalleryPhoto
}
```

### API Servisi

```swift
class GalleryService {
    static let shared = GalleryService()
    private let maxPhotos = 10

    // Kendi galerini getir
    func getMyGallery() async throws -> [GalleryPhoto] {
        let response: GalleryListResponse = try await APIClient.shared.get("/me/gallery")
        return response.data
    }

    // Baska profilin galerisini getir
    func getProfileGallery(profileId: String) async throws -> [GalleryPhoto] {
        let response: GalleryListResponse = try await APIClient.shared.get(
            "/profiles/\(profileId)/gallery"
        )
        return response.data
    }

    // Fotograf yukle
    func uploadPhoto(imageData: Data, caption: String? = nil) async throws -> GalleryPhoto {
        var formData = MultipartFormData()
        formData.append(imageData, name: "photo", fileName: "gallery_photo.jpg", mimeType: "image/jpeg")

        if let caption = caption {
            formData.append(caption.data(using: .utf8)!, name: "caption")
        }

        let response: GalleryUploadResponse = try await APIClient.shared.upload(
            "/me/gallery",
            formData: formData
        )
        return response.data
    }

    // Fotograf sil
    func deletePhoto(photoId: String) async throws {
        try await APIClient.shared.delete("/me/gallery/\(photoId)")
    }
}
```

### SwiftUI View

```swift
struct GalleryGridView: View {
    let profileId: String?
    let isOwnProfile: Bool

    @State private var photos: [GalleryPhoto] = []
    @State private var showImagePicker = false
    @State private var isUploading = false
    @State private var showDeleteAlert = false
    @State private var photoToDelete: GalleryPhoto?

    private let columns = Array(repeating: GridItem(.flexible(), spacing: 4), count: 3)
    private let maxPhotos = 10

    var body: some View {
        LazyVGrid(columns: columns, spacing: 4) {
            ForEach(photos) { photo in
                AsyncImage(url: URL(string: photo.url)) { image in
                    image
                        .resizable()
                        .aspectRatio(1, contentMode: .fill)
                        .clipped()
                } placeholder: {
                    Color.gray.opacity(0.3)
                }
                .frame(height: 120)
                .cornerRadius(8)
                .contextMenu {
                    if isOwnProfile {
                        Button(role: .destructive) {
                            photoToDelete = photo
                            showDeleteAlert = true
                        } label: {
                            Label("Sil", systemImage: "trash")
                        }
                    }
                }
            }

            // Ekleme butonu
            if isOwnProfile && photos.count < maxPhotos {
                Button {
                    showImagePicker = true
                } label: {
                    RoundedRectangle(cornerRadius: 8)
                        .strokeBorder(style: StrokeStyle(lineWidth: 2, dash: [6]))
                        .foregroundColor(.gray)
                        .frame(height: 120)
                        .overlay {
                            if isUploading {
                                ProgressView()
                            } else {
                                Image(systemName: "plus")
                                    .font(.title)
                                    .foregroundColor(.gray)
                            }
                        }
                }
                .disabled(isUploading)
            }
        }
        .task { await loadPhotos() }
        .sheet(isPresented: $showImagePicker) {
            ImagePicker(onImageSelected: handleImageSelected)
        }
        .alert("Fotografi Sil", isPresented: $showDeleteAlert) {
            Button("Iptal", role: .cancel) {}
            Button("Sil", role: .destructive) {
                if let photo = photoToDelete {
                    Task { await deletePhoto(photo) }
                }
            }
        } message: {
            Text("Bu fotografi silmek istediginizden emin misiniz?")
        }
    }

    private func loadPhotos() async {
        do {
            if let profileId = profileId {
                photos = try await GalleryService.shared.getProfileGallery(profileId: profileId)
            } else {
                photos = try await GalleryService.shared.getMyGallery()
            }
        } catch {
            print("Gallery load error: \(error)")
        }
    }

    private func handleImageSelected(_ imageData: Data) {
        Task {
            isUploading = true
            defer { isUploading = false }

            do {
                let photo = try await GalleryService.shared.uploadPhoto(imageData: imageData)
                photos.append(photo)
            } catch {
                print("Upload error: \(error)")
            }
        }
    }

    private func deletePhoto(_ photo: GalleryPhoto) async {
        do {
            try await GalleryService.shared.deletePhoto(photoId: photo.id)
            photos.removeAll { $0.id == photo.id }
        } catch {
            print("Delete error: \(error)")
        }
    }
}
```

---

## UI/UX Onerileri

### Galeri Gorunumu
- 3 sutunlu grid layout
- Kare (1:1) aspect ratio
- 4px gap
- Rounded corners (8px)
- Profil sayfasinda "Galeri" section header

### Fotograf Ekleme
- "+" buton dashed border ile (grid icerisinde son eleman)
- Kamera ve galeri secenegi (Action Sheet)
- Yukleme sirasinda loading indicator
- Basarili yuklemede animasyonlu ekleme

### Fotograf Silme
- Long press (React Native) veya context menu (SwiftUI) ile silme
- Silme onay dialog'u
- Optimistic UI: once sil, hata olursa geri getir

### Limit Gosterimi
- Galeri basliginda "3/10" gibi counter goster
- 10'a ulasinca "+" butonunu gizle veya disable et

---

## Hata Yonetimi Tablosu

| HTTP | Durum | Kullaniciya Mesaj | Aksiyon |
|------|-------|-------------------|---------|
| 201 | Basarili yukleme | - | Galeriye fotografi ekle |
| 200 | Basarili silme | - | Galeriden fotografi kaldir |
| 401 | Auth hatasi | "Oturum suresi doldu" | Login sayfasina yonlendir |
| 403 | Baskasinin fotografini silme | "Bu isleme yetkiniz yok" | Toast mesaji goster |
| 404 | Fotograf bulunamadi | "Fotograf bulunamadi" | Galeriyi yenile |
| 422 | Validation hatasi | Hata mesajini goster | Hata alanini vurgula |
| 422 | 10 foto limiti | "En fazla 10 fotograf yukleyebilirsiniz" | Toast/Alert goster |
| 500 | Sunucu hatasi | "Bir hata olustu, tekrar deneyin" | Retry butonu goster |

---

## Test Senaryolari

| # | Senaryo | Beklenen Sonuc |
|---|---------|----------------|
| 1 | Business kullanici fotograf yukler | 201, galeri guncellenir |
| 2 | Community kullanici fotograf yukler | 201, galeri guncellenir |
| 3 | Caption ile fotograf yukler | 201, caption goruntulenir |
| 4 | Caption olmadan fotograf yukler | 201, caption null |
| 5 | 10 fotograf varken 11. ekleme | 422, hata mesaji |
| 6 | PDF dosya yukleme | 422, validation hatasi |
| 7 | 6MB fotograf yukleme | 422, boyut hatasi |
| 8 | Kendi fotografini silme | 200, galeriden kalkar |
| 9 | Baskasinin fotografini silme | 403, fotograf silinmez |
| 10 | Baska profilin galerisini goruntuleme | 200, fotograflar listelenir |
| 11 | Bos galeri goruntuleme | 200, bos array |
| 12 | Auth olmadan istek | 401 |

---

## Desteklenen Dosya Formatlari

| Format | MIME Type | Max Boyut |
|--------|-----------|-----------|
| JPEG | image/jpeg | 5MB |
| JPG | image/jpg | 5MB |
| PNG | image/png | 5MB |
| GIF | image/gif | 5MB |
| WebP | image/webp | 5MB |
