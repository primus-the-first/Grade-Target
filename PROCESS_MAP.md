# System Process Map

This document outlines the architecture and data flow for the key features of the Grade Target application.

## 1. AI Tutor

This is the most complex feature, providing an interactive chat interface with file upload capabilities and conversation history.

**Files:**
- Frontend: `tutor.html`
- Frontend Logic: `tutor.js`
- Backend Logic: `server.php`

### Frontend Flow (`tutor.js`)

1.  **Initialization**:
    -   On page load (`DOMContentLoaded`), the script makes a `fetch` call to `server.php?action=history`.
    -   The sidebar (`#chat-history-container`) is populated with the list of past conversations returned from the server.

2.  **User Interaction & Form Submission**:
    -   A user types a question into the `#question` input.
    -   Optionally, a user attaches a file (`.txt`, `.pdf`, `.docx`, `.pptx`, or an image) using the `#file-attachment` input. A preview of the filename appears.
    -   The user selects a learning style (e.g., "Understand") from the `#learningLevel` dropdown.
    -   On form submission (clicking "Send"):
        -   The script captures the form data (question, file, etc.) into a `FormData` object. **This happens immediately to prevent race conditions.**
        -   The user's typed message is immediately displayed in the chat window (`#chat-container`).
        -   A `fetch` POST request is sent to `server.php` with the `FormData` as the body.
        -   A typing indicator is shown, and the input fields are disabled.

3.  **Response Handling**:
    -   When the `fetch` request completes, the `finally` block executes, clearing the question and file inputs to prepare for the next message.
    -   The JSON response from the server is parsed.
    -   If `success: true`, the AI's formatted answer (`result.answer`) is displayed in the chat window.
    -   If it was the first message of a new chat, the `conversation_id` from the response is stored in a hidden input (`#conversation_id`), and the chat history in the sidebar is refreshed to show the new chat title.

4.  **History Management**:
    -   **New Chat**: Clicking the "New Chat" button clears the `conversation_id`, resets the chat window with a welcome message, and deselects any active chat in the sidebar.
    -   **Load Chat**: Clicking a conversation in the sidebar triggers `loadConversation(id)`, which fetches that specific chat's history from `server.php?action=get_conversation&id={id}` and renders the messages.
    -   **Delete Chat**: Clicking the trash icon on a conversation triggers `deleteConversation(id)`, which sends a request to `server.php?action=delete_conversation&id={id}` and refreshes the sidebar.

### Backend Flow (`server.php`)

1.  **Session Start**: `session_start()` is called to access or create user-specific conversation data stored in `$_SESSION['conversations']`.

2.  **Request Routing**:
    -   **GET Requests**: The script checks for an `action` parameter to handle history management (fetching all titles, a single conversation, or deleting one). These actions read from or modify the `$_SESSION['conversations']` array.
    -   **POST Requests**: This is the main chat logic.

3.  **Conversation Handling**:
    -   It checks for a `conversation_id` in the POST data.
    -   If no ID exists, it creates a new conversation entry in `$_SESSION['conversations']` with a unique ID.

4.  **File & Prompt Processing**:
    -   It checks if a file was uploaded in `$_FILES['attachment']`.
    -   If a file exists, it's passed to `prepareFileParts()`.
        -   **Images**: The function base64-encodes the image data and prepares a multi-part payload for the Gemini API.
        -   **Documents**: The function uses libraries like `PdfParser` and `PhpWord` to extract text. The extracted text is prepended to the user's question to form a single, context-rich prompt.
    -   If no file is uploaded, the user's question is used as a simple text prompt.

5.  **Gemini API Call**:
    -   The entire chat history (including the new user message) is assembled into the `contents` array.
    -   A detailed `system_instruction` (system prompt) is included to guide the AI's behavior based on the selected `learningLevel`.
    -   The payload is sent to the Gemini API via cURL.

6.  **Response & History Update**:
    -   The AI's text response is received.
    -   The response is added to the current conversation's history in the `$_SESSION` array with the role "model".
    -   If it's the first turn of a new chat, a second, quick API call is made to generate a title for the conversation, which is then stored.
    -   The AI's response is formatted using `Parsedown` to convert Markdown into HTML.
    -   The final JSON object, including the HTML answer and `conversation_id`, is sent back to the frontend.

---

## 2. CGPA Calculator

**Files:**
- Backend Logic: `calculate.php`

### Flow

1.  **Request**: The frontend sends a POST request to `calculate.php` with arrays of `course_name`, `credit_hours`, and `grade`.
2.  **Processing**:
    -   The script iterates through the submitted courses.
    -   It validates each entry and calculates the total grade points and total credit hours based on the UCC grading scale.
3.  **Response**:
    -   The final CGPA is calculated (`totalGradePoints / totalCredits`).
    -   A `getClassification` function determines the student's academic standing (e.g., "First Class").
    -   The script returns a JSON object containing the calculated CGPA, classification, and detailed statistics.

---

## 3. Grade Predictor

**Files:**
- Backend Logic: `predict.php`

### Flow

1.  **Request**: The frontend sends a POST request to `predict.php` with `request_type: 'predict'`, along with the user's `current_cgpa`, `completed_credits`, `remaining_courses`, and `target_class`.
2.  **Processing**:
    -   The script calculates the minimum average grade required for the remaining courses to achieve the target class.
    -   A recursive function `generateAllCombinationsRecursive` explores all possible grade combinations (e.g., getting 3 A's and 2 B's) that meet or exceed this minimum required average.
    -   It sorts and samples these combinations to provide a few representative "paths" (e.g., high-effort, medium-effort).
3.  **Response**:
    -   The script returns a JSON object containing an initial summary (highest possible class, etc.) and the `grade_combinations_by_class`, which details the different grade distributions a student could aim for.