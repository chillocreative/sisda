import 'dart:io';
import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

class KpData {
  final String? icNumber;
  final String? name;
  final String? address;

  KpData({this.icNumber, this.name, this.address});
}

class OcrService {
  static final _textRecognizer = TextRecognizer();

  static Future<KpData> extractFromImage(File imageFile) async {
    final inputImage = InputImage.fromFile(imageFile);
    final recognizedText = await _textRecognizer.processImage(inputImage);

    String? icNumber;
    String? name;
    List<String> addressLines = [];

    for (TextBlock block in recognizedText.blocks) {
      for (TextLine line in block.lines) {
        final text = line.text.trim();

        // Extract IC number (12 digits, possibly with dashes)
        final icMatch = RegExp(r'(\d{6}[-\s]?\d{2}[-\s]?\d{4})').firstMatch(text);
        if (icMatch != null && icNumber == null) {
          icNumber = icMatch.group(1)!.replaceAll(RegExp(r'[-\s]'), '');
        }

        // Extract name (usually uppercase text after IC, before address)
        if (icNumber != null && name == null && text == text.toUpperCase() && text.length > 5) {
          if (!RegExp(r'^\d').hasMatch(text) &&
              !text.contains('WARGANEGARA') &&
              !text.contains('MALAYSIA') &&
              !text.contains('KAD PENGENALAN') &&
              !text.contains('IDENTITY')) {
            name = text;
          }
        }

        // Collect address lines (after name, contains numbers or common address words)
        if (name != null && (
            RegExp(r'\d').hasMatch(text) ||
            text.contains('JALAN') ||
            text.contains('TAMAN') ||
            text.contains('KAMPUNG') ||
            text.contains('LORONG')
        )) {
          addressLines.add(text);
        }
      }
    }

    return KpData(
      icNumber: icNumber,
      name: name,
      address: addressLines.isNotEmpty ? addressLines.join(', ') : null,
    );
  }

  static void dispose() {
    _textRecognizer.close();
  }
}
