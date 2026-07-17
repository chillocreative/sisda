import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/features/voters/voter_detail_screen.dart';

void main() {
  testWidgets('shows nama and displays masked fields verbatim', (tester) async {
    final voter = Voter.fromJson({
      'id': 5, 'nama': 'Ahmad bin Ali', 'no_ic': '****', 'no_tel': '****',
      'kadun': 'BULOH KASAP',
    });
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp(home: VoterDetailScreen(voter: voter)),
    ));
    expect(find.text('Ahmad bin Ali'), findsOneWidget);
    expect(find.text('****'), findsWidgets); // masked fields shown as-is
    expect(find.text('BULOH KASAP'), findsOneWidget);
    expect(find.text('Culaan baru untuk pengundi ini'), findsOneWidget);
  });
}
