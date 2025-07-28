# 🎓 Grade Target - UCC CGPA Calculator

> **A visually stunning CGPA calculator and class predictor designed specifically for University of Cape Coast (UCC) students**

![Grade Target Banner](https://via.placeholder.com/800x200/667eea/ffffff?text=Grade+Target+-+UCC+CGPA+Calculator)

## ✨ Features

### 🎯 **Core Functionality**
- **Dynamic Course Input**: Add/remove courses with smooth animations
- **Real-time CGPA Calculation**: Instant results using UCC grading scale
- **Class Classification**: Automatic degree class determination
- **Target Class Predictor**: Calculate required GPA for desired class
- **Course Breakdown**: Detailed analysis of each course contribution

### 🎨 **Visual Appeal**
- **Modern Design**: Gradient backgrounds and card-based layout
- **Responsive**: Perfect on desktop, tablet, and mobile devices
- **Interactive Animations**: Smooth transitions and hover effects
- **Color-coded Results**: Visual feedback for different grade classifications
- **Beautiful Typography**: Clean, readable fonts throughout

### 📊 **UCC Grading System**
| Grade | Grade Point | Description |
|-------|-------------|-------------|
| A     | 4.0         | Excellent   |
| B+    | 3.5         | Very Good   |
| B     | 3.0         | Good        |
| C+    | 2.5         | Fairly Good |
| C     | 2.0         | Average     |
| D     | 1.0         | Pass        |
| E/F   | 0.0         | Fail        |

### 🏆 **Class Classifications**
| Class | CGPA Range | Achievement |
|-------|------------|-------------|
| **First Class** | 3.6 - 4.0 | 👑 Outstanding |
| **Second Class Upper** | 3.0 - 3.59 | 🥇 Excellent |
| **Second Class Lower** | 2.5 - 2.99 | 🥈 Good |
| **Third Class** | 2.0 - 2.49 | 🥉 Satisfactory |
| **Pass** | 1.0 - 1.99 | ✅ Minimum |
| **Fail** | 0.0 - 0.99 | ❌ Below Standard |

## 🚀 Quick Start

### Option 1: PHP Built-in Server (Recommended)
```bash
# Navigate to project directory
cd /app

# Start PHP server
php -S localhost:8000

# Open in browser
open http://localhost:8000
```

### Option 2: Apache/Nginx
Simply place the files in your web server's document root and access via browser.

### Option 3: Online Demo
Upload files to any web hosting service that supports PHP.

## 📁 File Structure

```
/app/
├── index.html          # Main application interface
├── style.css           # Modern styling and animations
├── script.js           # Interactive functionality
├── calculate.php       # CGPA calculation engine
├── README.md           # This documentation
└── server.php          # Optional PHP server script
```

## 🛠️ Technical Details

### **Frontend Technologies**
- **HTML5**: Semantic markup and accessibility
- **CSS3**: Flexbox, Grid, animations, and responsive design
- **JavaScript ES6+**: Modern syntax with async/await
- **Font Awesome**: Beautiful icons throughout
- **Google Fonts**: Professional typography

### **Backend Technologies**
- **PHP 7.4+**: Server-side calculations and validation
- **JSON**: Data exchange format

### **Key Features**
- **No Database Required**: Stateless calculations
- **Client-side Fallback**: Works even without PHP
- **Input Validation**: Both frontend and backend validation
- **Error Handling**: Comprehensive error management
- **Accessibility**: WCAG compliant design

## 🎮 How to Use

### 1. **Add Your Courses**
- Click "Add Course" to create new rows
- Enter course name, credit hours (1-6), and select grade
- Remove courses using the trash icon

### 2. **Calculate CGPA**
- Click "Calculate CGPA" to process your data
- View detailed results in the beautiful modal popup
- See course breakdown and statistics

### 3. **Predict Target Class**
- Enter your current CGPA and remaining credits
- Select your target degree class
- Get precise GPA requirements for remaining courses

### 4. **View Reference Information**
- Check the grading scale for grade point values
- Review class classifications and requirements
- Use as quick reference while planning

## 🎨 Design Philosophy

### **Student-Centric Design**
- **Engaging Visuals**: Bright gradients and modern aesthetics appeal to young users
- **Intuitive Interface**: No learning curve - students can start immediately
- **Mobile-First**: Optimized for smartphone usage patterns
- **Gamification Elements**: Achievement-style displays make results exciting

### **Visual Hierarchy**
- **Color Psychology**: Different colors for different grade ranges
- **Progressive Disclosure**: Information revealed when needed
- **Visual Feedback**: Immediate response to user actions
- **Accessibility**: High contrast and readable fonts

## 🔧 Customization

### **Modify Grading Scale**
Edit the `$gradeValues` array in `calculate.php`:
```php
$gradeValues = [
    'A' => 4.0,
    'B+' => 3.5,
    // ... add your custom grades
];
```

### **Update Class Classifications**
Modify the `getClassification()` function in `calculate.php`:
```php
function getClassification($cgpa) {
    if ($cgpa >= 3.6) {
        return ['name' => 'First Class', ...];
    }
    // ... your custom classifications
}
```

### **Styling Changes**
All styles are in `style.css`. Key variables:
- Primary gradient: `#667eea` to `#764ba2`
- Accent colors: Various for different elements
- Border radius: `20px` for cards, `8px` for inputs

## 🧪 Testing

### **Manual Testing Checklist**
- [ ] Add multiple courses with different grades
- [ ] Remove courses (ensuring minimum one remains)
- [ ] Submit form with invalid data
- [ ] Test mobile responsiveness
- [ ] Try target class predictor
- [ ] Verify CGPA calculations manually

### **Sample Test Data**
| Course | Credits | Grade | Expected GP |
|--------|---------|-------|-------------|
| Math 101 | 3 | A | 12.0 |
| Physics 201 | 4 | B+ | 14.0 |
| Chemistry 105 | 3 | B | 9.0 |
| **Total** | **10** | **-** | **35.0** |
| **CGPA** | **-** | **-** | **3.50** |

## 🐛 Troubleshooting

### **Common Issues**

**1. PHP Errors**
- Ensure PHP 7.4+ is installed
- Check error logs: `tail -f /var/log/apache2/error.log`
- Verify file permissions

**2. JavaScript Not Working**
- Check browser console for errors
- Ensure all files are in the same directory
- Verify internet connection for CDN resources

**3. Styling Issues**
- Clear browser cache
- Check if CSS file is loading properly
- Verify font and icon CDN connections

**4. Calculation Errors**
- Verify grade values match UCC standards
- Check input validation rules
- Test with known CGPA calculations

## 🚀 Deployment

### **Web Hosting**
1. Upload all files to your hosting provider
2. Ensure PHP support is enabled
3. Set proper file permissions (644 for files, 755 for directories)
4. Test all functionality

### **Local Development**
1. Install PHP locally
2. Use built-in server: `php -S localhost:8000`
3. Or set up Apache/Nginx locally

## 📈 Future Enhancements

### **Potential Features**
- [ ] **Save/Load Sessions**: Remember calculations
- [ ] **PDF Export**: Generate transcript-style reports
- [ ] **Grade Trend Analysis**: Track improvement over semesters
- [ ] **Multi-Semester Support**: Calculate cumulative CGPA
- [ ] **What-If Scenarios**: Test different grade combinations
- [ ] **Dark Mode**: Alternative color scheme
- [ ] **University Templates**: Support for other institutions

### **Technical Improvements**
- [ ] **Progressive Web App**: Offline functionality
- [ ] **Database Integration**: Persistent storage
- [ ] **API Endpoints**: RESTful services
- [ ] **User Authentication**: Personal accounts
- [ ] **Analytics**: Usage tracking and insights

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Report Bugs**: Use GitHub issues for bug reports
2. **Suggest Features**: Share ideas for improvements  
3. **Submit Pull Requests**: Code contributions welcome
4. **Improve Documentation**: Help make this README better
5. **Test & Feedback**: Use the app and share experiences

### **Development Setup**
```bash
git clone <repository-url>
cd grade-target
php -S localhost:8000
```

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🙏 Acknowledgments

- **University of Cape Coast**: For the grading system standards
- **Font Awesome**: For beautiful icons
- **Google Fonts**: For professional typography
- **UCC Students**: For inspiration and feedback

---

## 📞 Support

Need help? Have questions?

- 📧 **Email**: support@gradetarget.com
- 🐛 **Issues**: GitHub Issues page
- 💬 **Discussions**: GitHub Discussions
- 📚 **Wiki**: Check the project wiki

---

<div align="center">

**Made with ❤️ for UCC Students**

*Empowering academic excellence through beautiful, functional tools*

[🌟 Star this project](https://github.com/your-repo) | [🐛 Report Bug](https://github.com/your-repo/issues) | [💡 Request Feature](https://github.com/your-repo/issues)

</div>