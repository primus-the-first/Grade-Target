# 🎓 Grade Target - UCC CGPA Calculator & AI Tutor

> **A visually stunning CGPA calculator, class predictor, and AI-powered study assistant designed specifically for University of Cape Coast (UCC) students**

## ✨ Features

### 🎯 **CGPA Calculator & Predictor**
- **Dynamic Course Input**: Add or remove courses as needed.
- **Real-time CGPA Calculation**: Instantly see your CGPA based on the official UCC grading scale.
- **Class Prediction**: See your current class and predict what you need for your desired class.

### 🤖 **AI Tutor**
- **Interactive Study Assistance**: Ask any study-related question and get a detailed answer from an AI tutor.
- **Customizable Learning Levels**: Tailor the AI's response to your level of understanding using Bloom's Taxonomy.
- **Context-Aware Answers**: Upload a text file with study materials to get answers specific to your course content.

## 🚀 How to Use

### **CGPA Calculator**
1.  **Add Courses**: Click the "Add Course" button to add a new course row.
2.  **Enter Details**: For each course, enter the course name, credit hours, and your grade.
3.  **Calculate**: Click the "Calculate CGPA" button to see your current CGPA and class.

### **Class Predictor**
1.  **Enter Current Stats**: Input your current CGPA and the number of credit hours you have completed.
2.  **Set Your Goal**: Enter the number of courses you have remaining and select your desired class.
3.  **Predict**: Click "Predict My Class Paths" to see the grades you need to achieve your goal.

### **AI Tutor**
1.  **Ask a Question**: Navigate to the `tutor.html` page and type your question into the text area.
2.  **Set Learning Level**: Choose a learning level from the dropdown to specify how detailed you want the answer to be.
3.  **Provide Context (Optional)**: Upload a `.txt` file containing notes or other study materials for a more specific answer.
4.  **Generate Answer**: Click "Generate Answer" to get a response from the AI.

## 🛠️ Technologies Used

-   **Frontend**: HTML, CSS, JavaScript, Tailwind CSS, Font Awesome
-   **Backend**: PHP
-   **AI**: Google Gemini API

## 📁 File Structure

```
/
├── index.html          # Main application interface for the CGPA calculator
├── tutor.html          # The AI Tutor interface
├── style.css           # Shared styles for the application
├── script.js           # JavaScript for the CGPA calculator
├── tutor.js            # JavaScript for the AI Tutor
├── calculate.php       # Handles CGPA calculations
├── predict.php         # Handles class prediction
├── server.php          # Handles AI Tutor requests
└── README.md           # This documentation
```

## 📄 License

This project is open source and available under the [MIT License](LICENSE).
