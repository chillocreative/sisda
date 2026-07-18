import 'package:flutter/material.dart';
import '../../screens/webview_screen.dart';

/// The "WebView tail" of the native/WebView split: heavy, already-built web
/// screens (Dashboard, Laporan, Data Pengundi, Profil) are reachable from the
/// native shell as ordinary pushed routes, each auto-logged-in via
/// `WebViewScreen`'s existing `webAuthToken`/`webAuthUrl` wiring (2a). These
/// helpers only decide WHICH path to open — `WebViewScreen` itself owns all
/// loading/auth/navigation behaviour and is deliberately left untouched.
const pathDashboard = '/dashboard';
const pathLaporan = '/reports/hasil-culaan';
const pathDataPengundi = '/reports/data-pengundi';
const pathProfil = '/profile';

void openDashboard(BuildContext context) => _openWebView(context, pathDashboard);

void openLaporan(BuildContext context) => _openWebView(context, pathLaporan);

void openDataPengundi(BuildContext context) =>
    _openWebView(context, pathDataPengundi);

void openProfil(BuildContext context) => _openWebView(context, pathProfil);

void _openWebView(BuildContext context, String path) {
  Navigator.push(
    context,
    MaterialPageRoute(builder: (_) => WebViewScreen(path: path)),
  );
}
