import 'package:chezvous/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('affiche le nom de l\'application', (tester) async {
    await tester.pumpWidget(const MainApp());
    expect(find.text('ChezVous'), findsOneWidget);
  });
}
