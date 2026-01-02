# Frontend Notes - API Tugas

## 🆕 Fitur Baru yang Ditambahkan

### 1. Periode Deadline
- **tanggal_mulai**: Kapan tugas mulai bisa dikerjakan
- **tanggal_deadline**: Batas waktu pengumpulan
- Format: `YYYY-MM-DD HH:mm:ss` atau datetime-local dari input
- Validasi: deadline harus setelah/sama dengan tanggal mulai

### 2. File Detail untuk Guru
- **file_detail**: Upload file apapun (gambar soal, PDF, Word, dll)
- Maksimal: **100MB**
- Semua format file diperbolehkan
- Use case: "Pelajari bab 4. Berikut di bawah ini foto soalnya"

---

## 🔧 PENTING: Format id_target untuk FormData

**Backend sudah di-update untuk handle FormData!**

Karena FormData tidak bisa mengirim nested array dengan baik, gunakan **JSON.stringify()** untuk `id_target`:

```javascript
// ✅ CARA YANG BENAR untuk FormData:
formData.append('id_target', JSON.stringify([
  { kelas: '10', jurusan: 'RPL' },
  { kelas: '10', jurusan: 'TKJ' }
]));

// Untuk target siswa:
formData.append('id_target', JSON.stringify([1, 2, 3, 4]));

// ❌ JANGAN seperti ini (tidak akan work):
// formData.append('id_target[0][kelas]', '10');
// formData.append('id_target[0][jurusan]', 'RPL');
```

### Note untuk Boolean Values

Untuk `tampilkan_nilai`, bisa kirim sebagai string atau boolean:
```javascript
// Semua format ini valid:
formData.append('tampilkan_nilai', 'true');  // string
formData.append('tampilkan_nilai', true);     // boolean
formData.append('tampilkan_nilai', '1');      // string number
```

Backend akan otomatis parse JSON string dan boolean string menjadi format yang benar.

---

## 📚 Endpoint Helper untuk Siswa & Kelas

### 1. List Semua Siswa
```javascript
GET /api/siswa
Authorization: Bearer {token}

// Response:
{
  "berhasil": true,
  "total": 25,
  "data": [
    {
      "id": 1,
      "username": "siswa1",
      "name": "Ahmad",
      "kelas": "X",
      "jurusan": "TKJ"
    }
  ]
}
```

### 2. List Kelas yang Tersedia
```javascript
GET /api/siswa/kelas
Authorization: Bearer {token}

// Response:
{
  "berhasil": true,
  "total_kelas": 3,
  "data": [
    { "kelas": "X", "jurusan": "TKJ", "jumlah_siswa": 5 },
    { "kelas": "XI", "jurusan": "RPL", "jumlah_siswa": 8 }
  ]
}
```

### 3. Get Siswa by Kelas
```javascript
POST /api/siswa/by-kelas
Authorization: Bearer {token}
Content-Type: application/json

{
  "kelas": "XI",
  "jurusan": "TKJ"
}

// Response:
{
  "berhasil": true,
  "pencarian": { "kelas": "XI", "jurusan": "TKJ" },
  "ditemukan": 8,
  "data": [...]
}
```

**Note:** Backend otomatis normalize kelas/jurusan (uppercase + trim), jadi "xi" = "XI" = " XI ".

**Use Case:** Gunakan endpoint `/api/siswa/kelas` untuk populate dropdown kelas di form create tugas.

---    ## Endpoint: POST /api/tugas

    ### Request Format
    Gunakan `FormData` karena ada file upload.

    ### Form Fields

```javascript
const formData = new FormData();

// Required fields
formData.append('judul', 'Tugas Matematika Bab 4');
formData.append('target', 'kelas'); // atau 'siswa'

// ⚠️ PENTING: id_target harus di-stringify!
formData.append('id_target', JSON.stringify([
  { kelas: '10', jurusan: 'RPL' },
  { kelas: '10', jurusan: 'TKJ' }
])); // untuk target kelas

// ATAU untuk target siswa:
// formData.append('id_target', JSON.stringify([1, 2, 3, 4]));

formData.append('tipe_pengumpulan', 'link'); // atau 'langsung'

// Optional fields - FITUR BARU ✨
formData.append('deskripsi', 'Pelajari bab 4 halaman 45-50');
formData.append('file_detail', fileInput.files[0]); // ✨ File apa saja max 100MB
formData.append('tanggal_mulai', '2025-12-05 08:00:00'); // ✨ Periode mulai
formData.append('tanggal_deadline', '2025-12-10 23:59:59'); // ✨ Deadline
formData.append('tampilkan_nilai', 'true'); // atau 'false'
```    ### Contoh Request dengan Axios

    ```javascript
    const createTugas = async (tugasData) => {
    try {
        const formData = new FormData();
        
        formData.append('judul', tugasData.judul);
        formData.append('deskripsi', tugasData.deskripsi || '');
        formData.append('target', tugasData.target);
        formData.append('id_target', JSON.stringify(tugasData.id_target));
        formData.append('tipe_pengumpulan', tugasData.tipe_pengumpulan);
        
        // Optional
        if (tugasData.file_detail) {
        formData.append('file_detail', tugasData.file_detail);
        }
        if (tugasData.tanggal_mulai) {
        formData.append('tanggal_mulai', tugasData.tanggal_mulai);
        }
        if (tugasData.tanggal_deadline) {
        formData.append('tanggal_deadline', tugasData.tanggal_deadline);
        }
        formData.append('tampilkan_nilai', tugasData.tampilkan_nilai || false);
        
        const response = await axios.post('http://localhost:8000/api/tugas', formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
            'Authorization': `Bearer ${token}`
        }
        });
        
        return response.data;
    } catch (error) {
        console.error('Error creating tugas:', error.response?.data);
        throw error;
    }
    };
    ```

    ### Contoh Form Component (React/Vue)

    ```jsx
    <form onSubmit={handleSubmit}>
    {/* Judul */}
    <input 
        type="text" 
        name="judul" 
        placeholder="Judul Tugas"
        required 
    />
    
    {/* Deskripsi */}
    <textarea 
        name="deskripsi" 
        placeholder="Deskripsi tugas (opsional)"
        rows="4"
    />
    
    {/* File Upload - FITUR BARU ✨ */}
    <div>
        <label>Upload File (Gambar soal, PDF, dll)</label>
        <input 
        type="file" 
        name="file_detail"
        accept="*/*"
        onChange={(e) => setFile(e.target.files[0])}
        />
        <small>Maksimal 100MB, semua format file diperbolehkan</small>
    </div>
    
    {/* Target */}
    <select name="target" onChange={(e) => setTargetType(e.target.value)}>
        <option value="kelas">Per Kelas</option>
        <option value="siswa">Per Siswa</option>
    </select>
    
    {/* ID Target - Conditional */}
    {targetType === 'kelas' ? (
        <div>
        <label>Pilih Kelas:</label>
        {/* Multi-select untuk kelas */}
        <select multiple name="kelas">
            <option value='{"kelas":"10","jurusan":"RPL"}'>10 RPL</option>
            <option value='{"kelas":"10","jurusan":"TKJ"}'>10 TKJ</option>
            <option value='{"kelas":"11","jurusan":"RPL"}'>11 RPL</option>
        </select>
        </div>
    ) : (
        <div>
        <label>Pilih Siswa:</label>
        {/* Multi-select untuk siswa */}
        <select multiple name="siswa">
            {/* Load dari API siswa */}
        </select>
        </div>
    )}
    
    {/* Tipe Pengumpulan */}
    <select name="tipe_pengumpulan">
        <option value="link">Link</option>
        <option value="langsung">Langsung</option>
    </select>
    
    {/* Periode Tugas - FITUR BARU ✨ */}
    <div>
        <label>Tanggal Mulai:</label>
        <input 
        type="datetime-local" 
        name="tanggal_mulai"
        />
    </div>
    
    <div>
        <label>Deadline:</label>
        <input 
        type="datetime-local" 
        name="tanggal_deadline"
        />
    </div>
    
    {/* Tampilkan Nilai */}
    <label>
        <input 
        type="checkbox" 
        name="tampilkan_nilai"
        />
        Tampilkan nilai ke siswa
    </label>
    
    <button type="submit">Buat Tugas</button>
    </form>
    ```

    ### Validasi Frontend yang Perlu

    1. **Judul**: Required, max 255 karakter
    2. **Deskripsi**: Optional, textarea
    3. **File**: Optional, max 100MB ✨
    4. **Target**: Required, pilih salah satu (kelas/siswa)
    5. **ID Target**: Required, minimal 1 item dipilih
    6. **Tipe Pengumpulan**: Required, pilih salah satu
    7. **Tanggal Mulai**: Optional, datetime ✨
    8. **Tanggal Deadline**: Optional, harus setelah tanggal mulai ✨
    9. **Tampilkan Nilai**: Optional, boolean

    ### Response Success

    ```json
    {
    "berhasil": true,
    "pesan": "Tugas berhasil dibuat",
    "data": {
        "id": 1,
        "judul": "Tugas Matematika Bab 4",
        "deskripsi": "Pelajari bab 4",
        "file_detail": "tugas_files/1733395200_soal.jpg",
        "target": "kelas",
        "id_target": [{"kelas": "10", "jurusan": "RPL"}],
        "tipe_pengumpulan": "link",
        "tanggal_mulai": "2025-12-05T08:00:00.000000Z",
        "tanggal_deadline": "2025-12-10T23:59:59.000000Z",
        "tampilkan_nilai": false,
        "created_at": "2025-12-05T10:30:00.000000Z"
    }
    }
    ```

    ### Response Error

    ```json
    {
    "berhasil": false,
    "pesan": "The file detail must not be greater than 102400 kilobytes."
    }
    ```

    ### URL File yang Diupload

    Jika ada file diupload, akses melalui:
    ```
    http://localhost:8000/storage/tugas_files/1733395200_soal.jpg
    ```

    **Note**: Pastikan sudah jalankan `php artisan storage:link` untuk symlink storage.

    ### Tips UI/UX

    1. **File Preview**: Tampilkan preview gambar jika file yang diupload adalah gambar
    2. **Drag & Drop**: Implementasi drag & drop untuk file upload
    3. **Progress Bar**: Tampilkan progress saat upload file besar
    4. **Date Picker**: Gunakan library seperti react-datepicker atau vue-datepicker
    5. **Multi-select**: Gunakan library seperti react-select atau vue-multiselect untuk pilih kelas/siswa
    6. **Validation Feedback**: Tampilkan error message per field secara real-time
    7. **Loading State**: Disable tombol submit saat proses upload
    8. **Success Feedback**: Redirect atau tampilkan notifikasi setelah berhasil
