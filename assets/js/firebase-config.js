/**
 * Konfigurasi Firebase untuk Echo-Glance
 * Ganti nilai-nilai di bawah dengan kredensial dari Firebase Console Anda.
 */

const firebaseConfig = {
  apiKey: "AIzaSyD_QeIiO-6R7z0KpljAxxd688O1OnbmuHM",
  authDomain: "echo-glance.firebaseapp.com",
  projectId: "echo-glance",
  storageBucket: "echo-glance.firebasestorage.app",
  messagingSenderId: "298348753411",
  appId: "1:298348753411:web:a9490ece4e5d857e921436",
  measurementId: "G-JC33XE4FDE",
  databaseURL: "https://echo-glance-default-rtdb.asia-southeast1.firebasedatabase.app"
};

// Cek apakah library firebase sudah diload
if (typeof firebase !== 'undefined') {
    // Inisialisasi Firebase
    if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
    }
    console.log('[ECHO-GLANCE] Firebase diinisialisasi');

    // Inisialisasi Database dan Auth global
    window.db = firebase.database();
    window.auth = firebase.auth();
} else {
    console.warn('[ECHO-GLANCE] Library Firebase belum diload. Pastikan script SDK dipanggil sebelum config.');
}
