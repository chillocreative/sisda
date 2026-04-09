import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../services/api_service.dart';

class WebViewScreen extends StatefulWidget {
  final String path;
  final bool embedded;

  const WebViewScreen({super.key, required this.path, this.embedded = false});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late WebViewController _controller;
  bool _isLoading = true;
  String _currentPath = '';

  @override
  void initState() {
    super.initState();
    _currentPath = widget.path;
    _initWebView();
  }

  @override
  void didUpdateWidget(WebViewScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) {
      _currentPath = widget.path;
      // Session already established from initial load, navigate directly
      _controller.loadRequest(Uri.parse('${ApiService.baseUrl}$_currentPath'));
    }
  }

  void _initWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setUserAgent('SISDA-Mobile-App/1.0')
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) { if (mounted) setState(() => _isLoading = true); },
        onPageFinished: (_) {
          if (mounted) {
            setState(() => _isLoading = false);
          }
        },
        onNavigationRequest: (request) {
          if (request.url.contains('sistemdatapengundi.com')) {
            return NavigationDecision.navigate;
          }
          return NavigationDecision.prevent;
        },
      ));

    _loadWithAuth();
  }

  Future<void> _loadWithAuth() async {
    try {
      final webToken = await ApiService.getWebAuthToken();
      if (webToken != null && mounted) {
        final url = ApiService.getWebAuthUrl(webToken, _currentPath);
        _controller.loadRequest(Uri.parse(url));
        return;
      }
    } catch (_) {}

    // Fallback: load directly (will show login page if not authenticated)
    if (mounted) {
      _controller.loadRequest(Uri.parse('${ApiService.baseUrl}$_currentPath'));
    }
  }

  @override
  Widget build(BuildContext context) {
    return widget.embedded
      ? Stack(children: [
          WebViewWidget(controller: _controller),
          if (_isLoading) const Center(child: CircularProgressIndicator()),
        ])
      : Scaffold(
          appBar: AppBar(
            title: const Text('SISDA'),
            leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () async {
              if (await _controller.canGoBack()) {
                _controller.goBack();
              } else {
                if (mounted) Navigator.of(context).pop();
              }
            }),
          ),
          body: Stack(children: [
            WebViewWidget(controller: _controller),
            if (_isLoading) const Center(child: CircularProgressIndicator()),
          ]),
        );
  }
}
