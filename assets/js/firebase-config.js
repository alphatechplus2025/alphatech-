// ==========================================================================
// ALPHATECH+ FIREBASE & FIRESTORE CORE INITIALIZATION
// ==========================================================================

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js";
import { 
  getFirestore, 
  collection, 
  addDoc, 
  doc, 
  getDocs,
  setDoc,
  updateDoc, 
  deleteDoc,
  onSnapshot, 
  query, 
  orderBy, 
  serverTimestamp 
} from "https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js";

export const firebaseConfig = {
  apiKey: "AIzaSyCbd_16UIKRGUQsUc47Zl8ccsRrq0DWBw0",
  authDomain: "alpha-tech-plus.firebaseapp.com",
  projectId: "alpha-tech-plus",
  storageBucket: "alpha-tech-plus.firebasestorage.app",
  messagingSenderId: "1040509888533",
  appId: "1:1040509888533:web:5192a429834ed9ce95861c",
  measurementId: "G-MQQ6ZZ539D"
};

// Initialize Firebase App
export const app = initializeApp(firebaseConfig);

// Initialize Cloud Firestore Database
export const db = getFirestore(app);

// Export Firestore primitives for modular usage across scripts
export {
  collection,
  addDoc,
  doc,
  getDocs,
  setDoc,
  updateDoc,
  deleteDoc,
  onSnapshot,
  query,
  orderBy,
  serverTimestamp
};
