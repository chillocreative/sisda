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
      _controller.loadRequest(Uri.parse('${ApiService.baseUrl}$_currentPath'));
    }
  }

  void _initWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setUserAgent('SISDA-Mobile-App/1.0')
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) { if (mounted) setState(() => _isLoading = true); },
        onPageFinished: (_) { if (mounted) setState(() => _isLoading = false); },
        onNavigationRequest: (request) {
          // Keep navigation within the app
          if (request.url.contains('sistemdatapengundi.com')) {
            return NavigationDecision.navigate;
          }
          return NavigationDecision.prevent;
        },
      ));

    // Set cookies for authenticated session
    final cookieManager = WebViewCookieManager();
    if (ApiService.sessionCookie != null) {
      final parts = ApiService.sessionCookie!.split('=');
      if (parts.length >= 2) {
        cookieManager.setCookie(WebViewCookie(
          name: parts[0],
          value: parts.sublist(1).join('='),
          domain: 'sistemdatapengundi.com',
          path: '/',
        ));
      }
    }

    _controller.loadRequest(Uri.parse('${ApiService.baseUrl}$_currentPath'));
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
