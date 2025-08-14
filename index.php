<?php
// index.php - Unified Backend & Frontend
session_start();

// Database configuration
class Database {
    private $host = 'localhost';
    private $dbname = 'contacts_db';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
        }
    }

    public function getPdo() {
        return $this->pdo;
    }
}

class ContactManager {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function createContact($data) {
        $stmt = $this->db->getPdo()->prepare("INSERT INTO contacts (name, email, phone, notes) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['notes']]);
    }

    public function updateContact($data) {
        $stmt = $this->db->getPdo()->prepare("UPDATE contacts SET name = ?, email = ?, phone = ?, notes = ? WHERE id = ?");
        return $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['notes'], $data['id']]);
    }

    public function deleteContact($id) {
        $stmt = $this->db->getPdo()->prepare("DELETE FROM contacts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getContacts($search = '', $sort = 'newest') {
        $query = "SELECT * FROM contacts";
        $params = [];

        if ($search) {
            $query .= " WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR notes LIKE ?";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
        }

        switch ($sort) {
            case 'oldest':
                $query .= " ORDER BY created_at ASC";
                break;
            case 'name':
                $query .= " ORDER BY name ASC";
                break;
            case 'email':
                $query .= " ORDER BY email ASC";
                break;
            default:
                $query .= " ORDER BY created_at DESC";
        }

        $stmt = $this->db->getPdo()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getContactById($id) {
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Initialize ContactManager
$contactManager = new ContactManager();

// Handle API requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['api']) {
        case 'contacts':
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'newest';
            $contacts = $contactManager->getContacts($search, $sort);
            echo json_encode([
                'contacts' => $contacts,
                'count' => count($contacts)
            ]);
            exit;
            
        case 'contact':
            if (isset($_GET['id'])) {
                $contact = $contactManager->getContactById($_GET['id']);
                echo json_encode($contact ?: ['error' => 'Contact not found']);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $result = $contactManager->createContact($_POST);
                echo json_encode(['success' => $result, 'message' => 'Contact created successfully']);
                break;
            
            case 'update':
                $result = $contactManager->updateContact($_POST);
                echo json_encode(['success' => $result, 'message' => 'Contact updated successfully']);
                break;
            
            case 'delete':
                $result = $contactManager->deleteContact($_POST['id']);
                echo json_encode(['success' => $result, 'message' => 'Contact deleted successfully']);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// If we reach here, serve the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts CRUD - Modern Contact Management</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="description" content="Modern contact management system with CRUD operations">
    
</head>
<body data-theme="light">
    <div class="container">
        <header class="header">
            <div class="header-info">
                <h1>Contacts CRUD</h1>
                <p>Create • Read • Update • Delete</p>
            </div>
            <div class="header-controls">
                <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                    <span id="theme-icon">🌙</span>
                    <span id="theme-text">Dark</span>
                </button>
                <button class="btn btn-primary" onclick="showAddForm()" aria-label="Add new contact">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    New contact
                </button>
            </div>
        </header>

        <div class="card" id="contact-form" style="display: none;">
            <div class="card-header">
                <h2 id="form-title">Add a new contact</h2>
            </div>
            <div class="card-body">
                <form id="contact-form-element">
                    <input type="hidden" id="contact-id" name="id">
                    <input type="hidden" id="form-action" name="action" value="create">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="contact-name">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Name <span class="required">*</span>
                            </label>
                            <input type="text" id="contact-name" name="name" required placeholder="Enter full name" autocomplete="name">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact-email">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                Email
                            </label>
                            <input type="email" id="contact-email" name="email" placeholder="Enter email address" autocomplete="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact-phone">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            Phone
                        </label>
                        <input type="tel" id="contact-phone" name="phone" placeholder="Enter phone number" autocomplete="tel">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="contact-notes">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            Notes
                        </label>
                        <textarea id="contact-notes" name="notes" placeholder="Add any additional information or notes about this contact..."></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="hideForm()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20,6 9,17 4,12"></polyline>
                        </svg>
                        <span id="submit-text">Add contact</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="search-sort">
        <div class="search-box">
            <span class="search-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </span>
            <input type="text" id="search" placeholder="Search contacts..." oninput="debounceSearch()" autocomplete="off">
        </div>
        
        <select id="sort" class="sort-select" onchange="handleSort()">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="name">Name A-Z</option>
            <option value="email">Email A-Z</option>
        </select>
        
        <div class="results-info">
            <span id="results-count">0</span> results
        </div>
    </div>

    <div class="table-container">
        <div id="loading" class="loading" style="display: none;">
            <div class="spinner"></div>
        </div>
        
        <div id="contacts-content">
            <div id="empty-state" class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3>No contacts found</h3>
                <p>Start by adding your first contact using the button above.</p>
            </div>
            
            <table id="contacts-table" style="display: none;">
                <thead>
                    <tr>
                        <th>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Name
                        </th>
                        <th>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            Email
                        </th>
                        <th>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            Phone
                        </th>
                        <th>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            Notes
                        </th>
                        <th>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="notification-container"></div>

<script>
class ContactManager {
    constructor() {
        this.contacts = [];
        this.currentEditId = null;
        this.searchTimeout = null;
        this.init();
    }

    init() {
        this.loadTheme();
        this.loadContacts();
        this.bindEvents();
        this.checkUrlParams();
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        
        body.setAttribute('data-theme', savedTheme);
        
        if (savedTheme === 'dark') {
            themeIcon.textContent = '☀️';
            themeText.textContent = 'Light';
        } else {
            themeIcon.textContent = '🌙';
            themeText.textContent = 'Dark';
        }
    }

    toggleTheme() {
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        
        if (body.getAttribute('data-theme') === 'light') {
            body.setAttribute('data-theme', 'dark');
            themeIcon.textContent = '☀️';
            themeText.textContent = 'Light';
            localStorage.setItem('theme', 'dark');
        } else {
            body.setAttribute('data-theme', 'light');
            themeIcon.textContent = '🌙';
            themeText.textContent = 'Dark';
            localStorage.setItem('theme', 'light');
        }
        
        this.showNotification('Theme updated successfully!', 'success');
    }

    bindEvents() {
        document.getElementById('contact-form-element').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        document.getElementById('search').addEventListener('input', () => {
            this.debounceSearch();
        });

        document.getElementById('sort').addEventListener('change', () => {
            this.handleSort();
        });

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            if (e.key === 'Escape') {
                this.hideForm();
            }
        });
    }

    checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit');
        const search = urlParams.get('search');
        const sort = urlParams.get('sort');

        if (search) {
            document.getElementById('search').value = search;
        }
        if (sort) {
            document.getElementById('sort').value = sort;
        }
        if (editId) {
            this.editContact(parseInt(editId));
        }
    }

    async makeRequest(url, options = {}) {
        try {
            this.showLoading();
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    ...options.headers
                },
                ...options
            });
            
            const data = await response.json();
            this.hideLoading();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            return data;
        } catch (error) {
            this.hideLoading();
            this.showNotification(error.message, 'error');
            throw error;
        }
    }

    async loadContacts() {
        try {
            const search = document.getElementById('search').value;
            const sort = document.getElementById('sort').value;
            
            const data = await this.makeRequest(`?api=contacts&search=${encodeURIComponent(search)}&sort=${sort}`);
            
            this.contacts = data.contacts;
            this.renderContacts();
            this.updateResultsCount(data.count);
            
        } catch (error) {
            console.error('Error loading contacts:', error);
        }
    }

    async handleFormSubmit() {
        const form = document.getElementById('contact-form-element');
        const formData = new FormData(form);
        
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            params.append(key, value);
        }

        try {
            const data = await this.makeRequest('', {
                method: 'POST',
                body: params
            });

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.hideForm();
                this.loadContacts();
                this.updateUrl();
            }
        } catch (error) {
            console.error('Error submitting form:', error);
        }
    }

    async editContact(id) {
        try {
            const data = await this.makeRequest(`?api=contact&id=${id}`);
            
            if (data && !data.error) {
                this.currentEditId = id;
                this.fillForm(data);
                this.showForm(true);
            }
        } catch (error) {
            console.error('Error loading contact:', error);
        }
    }

    async deleteContact(id, name) {
        if (!confirm(`Are you sure you want to delete ${name}? This action cannot be undone.`)) {
            return;
        }

        const params = new URLSearchParams();
        params.append('action', 'delete');
        params.append('id', id);

        try {
            const data = await this.makeRequest('', {
                method: 'POST',
                body: params
            });

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.loadContacts();
            }
        } catch (error) {
            console.error('Error deleting contact:', error);
        }
    }

    showForm(isEdit = false) {
        const form = document.getElementById('contact-form');
        const title = document.getElementById('form-title');
        const submitText = document.getElementById('submit-text');
        const action = document.getElementById('form-action');

        form.style.display = 'block';
        
        if (isEdit) {
            title.textContent = 'Edit contact';
            submitText.textContent = 'Update contact';
            action.value = 'update';
        } else {
            title.textContent = 'Add a new contact';
            submitText.textContent = 'Add contact';
            action.value = 'create';
        }

        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        setTimeout(() => {
            document.getElementById('contact-name').focus();
        }, 300);
    }

    hideForm() {
        document.getElementById('contact-form').style.display = 'none';
        this.clearForm();
        this.currentEditId = null;
        this.updateUrl();
    }

    fillForm(contact) {
        document.getElementById('contact-id').value = contact.id;
        document.getElementById('contact-name').value = contact.name || '';
        document.getElementById('contact-email').value = contact.email || '';
        document.getElementById('contact-phone').value = contact.phone || '';
        document.getElementById('contact-notes').value = contact.notes || '';
    }

    clearForm() {
        document.getElementById('contact-form-element').reset();
        document.getElementById('contact-id').value = '';
    }

    renderContacts() {
        const table = document.getElementById('contacts-table');
        const tbody = document.getElementById('contacts-tbody');
        const emptyState = document.getElementById('empty-state');

        if (this.contacts.length === 0) {
            table.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        table.style.display = 'table';
        emptyState.style.display = 'none';

        tbody.innerHTML = this.contacts.map(contact => `
            <tr>
                <td><strong>${this.escapeHtml(contact.name)}</strong></td>
                <td>${contact.email ? `<a href="mailto:${this.escapeHtml(contact.email)}" style="color: var(--accent); text-decoration: none;">${this.escapeHtml(contact.email)}</a>` : '<span style="color: var(--text-muted);">—</span>'}</td>
                <td>${contact.phone ? `<a href="tel:${this.escapeHtml(contact.phone)}" style="color: var(--accent); text-decoration: none;">${this.escapeHtml(contact.phone)}</a>` : '<span style="color: var(--text-muted);">—</span>'}</td>
                <td><span title="${this.escapeHtml(contact.notes || '')}" style="color: var(--text-secondary);">${contact.notes ? this.truncateText(this.escapeHtml(contact.notes), 50) : '<span style="color: var(--text-muted);">—</span>'}</span></td>
                <td>
                    <div class="actions">
                        <button class="btn btn-sm btn-secondary" onclick="contactManager.editContact(${contact.id})" title="Edit contact">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="contactManager.deleteContact(${contact.id}, '${this.escapeHtml(contact.name)}')" title="Delete contact">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3,6 5,6 21,6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    debounceSearch() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.loadContacts();
            this.updateUrl();
        }, 300);
    }

    handleSort() {
        this.loadContacts();
        this.updateUrl();
    }

    updateUrl() {
        const search = document.getElementById('search').value;
        const sort = document.getElementById('sort').value;
        const url = new URL(window.location);
        
        url.searchParams.delete('edit');
        
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        
        url.searchParams.set('sort', sort);
        
        window.history.replaceState({}, '', url.toString());
    }

    showLoading() {
        document.getElementById('loading').style.display = 'flex';
        document.getElementById('contacts-content').style.opacity = '0.5';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('contacts-content').style.opacity = '1';
    }

    showNotification(message, type = 'success') {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    ${type === 'success' ? 
                        '<polyline points="20,6 9,17 4,12"></polyline>' : 
                        '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>'
                    }
                </svg>
                <span>${message}</span>
            </div>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => container.removeChild(notification), 300);
        }, 4000);
    }

    updateResultsCount(count) {
        document.getElementById('results-count').textContent = count;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
}

const contactManager = new ContactManager();

function toggleTheme() {
    contactManager.toggleTheme();
}

function showAddForm() {
    contactManager.showForm();
}

function hideForm() {
    contactManager.hideForm();
}

function debounceSearch() {
    contactManager.debounceSearch();
}

function handleSort() {
    contactManager.handleSort();
}
</script>
</body>
</html>
                                