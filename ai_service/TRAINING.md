# CV Parser Training & Improvement Guide

This guide explains how to use the collected dataset to improve the accuracy of the CV Parser. The parser is designed to **analyze any type of CV** (exported PDF, Word, scanned, graphical, multi-column, French/English/Arabic, etc.) and fill **WorkerProfile** attributes: title, bio, hourly_rate, experience_years, location, skills.

## 1. Data Collection
As users upload CVs and save their profiles, the system automatically builds a dataset in `ai_service/dataset/`.
- Ensure that the JSON files in `dataset/files/` match the real CV content. You can manually edit these files to "correct" the labels.
- **Important for "all types of CV"**: Add diverse CVs to the dataset — not only PDFs exported from Worker Profile. Include:
  - Different layouts (classic, modern, multi-column, graphical)
  - Different languages (French, English, Arabic)
  - Scanned PDFs, Word exports, and image CVs
  - Various job sectors (tech, trades, services)
  So the model (and few-shot examples) generalize to any format.

## 2. Evaluation
Before retraining, see how well the current model performs:
```bash
python ai_service/evaluator.py
```
This will show you which fields (like Bio or Hourly Rate) the model is struggling with.

## 3. Method A: Few-Shot Prompting (Fast & Automatic)
The parser is now equipped with **Few-Shot Prompting**. It automatically loads the 2 most recent examples from your dataset and includes them in every new extraction request to guide Gemini.
- **Tip**: To improve this immediately, manually create 2-3 "perfect" examples in the dataset folder.

## 4. Method B: Gemini Fine-Tuning (Best Accuracy)
If Few-Shot prompting isn't enough, you can fine-tune a custom Gemini model.

### Step 1: Convert Dataset
Run the converter to create a file ready for Google AI Studio:
```bash
python ai_service/dataset_converter.py
```
This produces `ai_service/gemini_finetune.jsonl`.

### Step 2: Fine-Tune in Google AI Studio
1. Go to [Google AI Studio](https://aistudio.google.com/).
2. Click **Create New** > **Tuned Model**.
3. Upload `gemini_finetune.jsonl` as your training data.
4. Select `gemini-1.5-flash` or `gemini-2.0-flash` as the base model.
5. Once training is complete, copy your **Model ID** (e.g., `tunedModels/my-cv-parser-abc`).

### Step 3: Use the Tuned Model
Update your `ai_service/.env` file:
```env
GEMINI_MODEL_ID=tunedModels/your-tuned-model-id
```

The `cv_parser.py` will automatically use this model if the environment variable is set.
