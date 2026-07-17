import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../data/remote/mobile_api.dart';
import '../../models/api_result.dart';
import '../../models/voter.dart';
import '../../providers.dart';
import '../../services/ocr_service.dart';
import 'voter_detail_screen.dart';

/// Outcome of resolving a scanned/entered IC against the API. Sealed so
/// `startScanAndLookup` (and any future caller) is forced to handle every
/// case — no silent "unknown" fallthrough.
sealed class IcLookupResult {
  const IcLookupResult();
}

class IcFound extends IcLookupResult {
  final Voter voter;
  const IcFound(this.voter);
}

class IcNotFound extends IcLookupResult {
  const IcNotFound();
}

class IcError extends IcLookupResult {
  final String bmMessage;
  const IcError(this.bmMessage);
}

/// The testable seam: given an IC and an API client, decide found / not
/// found / error. A 404 means "no such voter" (BM not-found copy at the
/// call site); any other `ApiException` — including a network failure,
/// where `status == null` — becomes `IcError` with `firstError()`, which is
/// always Bahasa Melayu. `rawMessage` (possibly English, e.g.
/// 'SocketException') must never reach `IcError.bmMessage`.
Future<IcLookupResult> resolveIc(String ic, MobileApi api) async {
  try {
    final voter = await api.showVoter(ic);
    return IcFound(voter);
  } on ApiException catch (e) {
    if (e.status == 404) return const IcNotFound();
    return IcError(e.firstError());
  }
}

/// Camera → OCR → lookup → navigate. The camera capture itself
/// (`ImagePicker.pickImage`) depends on a platform plugin and is exercised
/// on-device/emulator only — it is deliberately NOT unit-tested here (no
/// fake camera); `resolveIc` above is the tested seam for everything after
/// the image is captured.
Future<void> startScanAndLookup(BuildContext context, WidgetRef ref) async {
  final messenger = ScaffoldMessenger.of(context);

  // Emulator-tested only (ImagePicker is a platform plugin) — not covered
  // by `flutter test`.
  final picker = ImagePicker();
  final image = await picker.pickImage(source: ImageSource.camera, imageQuality: 90);
  if (image == null) return;

  final kpData = await OcrService.extractFromImage(File(image.path));
  final ic = kpData.icNumber;
  if (ic == null) {
    messenger.showSnackBar(const SnackBar(
        content: Text('Tidak dapat membaca No. IC dari gambar. Sila cuba lagi.')));
    return;
  }

  final api = ref.read(mobileApiProvider);
  final result = await resolveIc(ic, api);

  if (!context.mounted) return;

  switch (result) {
    case IcFound(:final voter):
      Navigator.push(context, MaterialPageRoute(builder: (_) => VoterDetailScreen(voter: voter)));
    case IcNotFound():
      messenger.showSnackBar(const SnackBar(content: Text('Pengundi tidak dijumpai.')));
    case IcError(:final bmMessage):
      messenger.showSnackBar(SnackBar(content: Text(bmMessage)));
  }
}
