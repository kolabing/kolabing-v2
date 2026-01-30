# Task: Profile Gallery Photos

## Status
- Created: 2026-01-30 17:00
- Started: 2026-01-30 17:00
- Completed:

## Description
Business and community profiles need a gallery-style photo upload feature. Users should be able to upload multiple photos to their profile gallery.

## Assigned Agents
- [x] @backend-developer - Migration, model, controller, service
- [x] @laravel-specialist - Tests
- [x] @api-designer - API contract

## Progress
### API Contract
- POST /api/v1/me/gallery - Upload photo
- GET /api/v1/me/gallery - List own gallery
- DELETE /api/v1/me/gallery/{photo} - Delete photo
- GET /api/v1/profiles/{profile}/gallery - View profile gallery (public)

### Backend
- Migration: profile_gallery_photos table
- Model: ProfileGalleryPhoto
- Service: Update FileUploadType enum, gallery methods in ProfileService
- Controller: GalleryController
- FormRequest: UploadGalleryPhotoRequest
- Resource: GalleryPhotoResource

## Notes
- Max 10 photos per profile
- Reuse existing FileUploadService
- Add new FileUploadType::GalleryPhoto
