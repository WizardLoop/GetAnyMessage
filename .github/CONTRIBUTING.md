# 🤝 Contributing to JewishPulse

Thank you for considering contributing to **JewishPulse**! We greatly appreciate your effort to help improve this open-source project. This document will guide you through the process of contributing, including how to add new features and support for additional languages.

## 🧩 Ways to Contribute

There are many ways you can contribute to this project:

- 🐛 Reporting bugs
- 💡 Proposing new features
- 🌍 Adding or improving translations
- 🧪 Writing tests
- 🧼 Improving documentation

## 🌐 Adding a New Language

JewishPulse uses JSON files for localization. Here's how to add support for a new language:

1. Navigate to the `app/locales/` directory.
2. Create a new file for your language using its ISO 639-1 code, for example:
   - `fr.json` for French
   - `es.json` for Spanish
3. Copy the contents of `en.json` as a template.
4. Translate the values (not the keys) to the target language.
5. Save the file and verify formatting (use a JSON linter or formatter if needed).

Please make sure your translations are accurate and complete before submitting a pull request.

## 🚀 Suggesting a New Feature

1. Fork the repository to your own GitHub account.
2. Create a new branch for your feature:  
   ```bash
   git checkout -b feature/my-new-feature
   ```
3. Implement your feature.
4. Commit your changes and push your branch:
   ```bash
   git add .
   git commit -m "Add new feature: My awesome idea"
   git push origin feature/my-new-feature
   ```
5. Open a Pull Request (PR) on the original repository:
   - Include a clear description of what the feature does.
   - Reference related issues if applicable.

## ✅ Code Guidelines

- Follow PSR-12 coding standards.
- Include comments where needed.
- Test your code before submitting.

## 🧪 Testing Your Changes

Make sure your code runs inside the Docker environment:
```bash
make up
make logs
```
You can use `make sh` to enter the container and test directly.

## 📬 Get in Touch

If you have any questions or need guidance, feel free to open an issue or start a discussion in the repository.

Happy contributing! ✨