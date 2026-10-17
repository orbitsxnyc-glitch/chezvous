// Génère une image PNG de chaque écran pour contrôle visuel : `SHOTS_DIR=/chemin flutter test test/screenshots`.
// Sans SHOTS_DIR, les tests sont ignorés (la CI n'écrit aucun fichier).
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:chezvous/advertiser/editor_repository.dart';
import 'package:chezvous/advertiser/listing_editor.dart';
import 'package:chezvous/advertiser/my_listings.dart';
import 'package:chezvous/auth/auth_controller.dart';
import 'package:chezvous/auth/auth_screens.dart';
import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/engagement/alerts_screen.dart';
import 'package:chezvous/engagement/favorites_screen.dart';
import 'package:chezvous/home/home_screen.dart';
import 'package:chezvous/inquiries/inquiries_repository.dart';
import 'package:chezvous/inquiries/inquiries_screens.dart';
import 'package:chezvous/listings/listing_detail_screen.dart';
import 'package:chezvous/listings/listings_repository.dart';
import 'package:chezvous/listings/search_screen.dart';
import 'package:chezvous/shell/app_shell.dart';
import 'package:chezvous/ui/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

final _dir = Platform.environment['SHOTS_DIR'];

Map<String, dynamic> _summary(int i, {String state = 'available'}) => {
  'id': 'ulid-$i',
  'reference': 'CV-2026-00010$i',
  'transaction': 'rent_long',
  'title': [
    'Appartement F3 lumineux',
    'Villa avec jardin',
    'Studio meublé centre',
    'F4 vue mer',
  ][i % 4],
  'price_xof': 250000 + i * 75000,
  'negotiable': i.isEven,
  'property_type': ['Appartement', 'Villa', 'Studio', 'Appartement'][i % 4],
  'locality': {
    'name': ['Yoff', 'Almadies', 'Ouakam', 'Mermoz'][i % 4],
    'slug': 'x',
  },
  'surface_m2': 60 + i * 20,
  'bedrooms': 1 + i % 4,
  'furnished': i % 3 == 0,
  'agency': i == 1
      ? {'name': 'Agence Teranga', 'slug': 'teranga', 'verified': true}
      : null,
  'cover': null,
  'availability': {
    'state': state,
    'confirmed_at': DateTime.now()
        .subtract(Duration(hours: 5 + i * 30))
        .toUtc()
        .toIso8601String(),
  },
};

Map<String, dynamic> _detail() => {
  ..._summary(1),
  'description': 'Bel appartement traversant au 3e étage, proche des commerces et des transports. Cuisine équipée, deux balcons, gardien.',
  'entry_costs': {
    'rent_xof': 325000,
    'deposit_xof': 650000,
    'advance_xof': 325000,
    'agency_fee_xof': 325000,
    'other_fees_xof': null,
    'disclaimer':
        "Montants renseignés par l'annonceur, à confirmer avant tout paiement.",
  },
  'property': {
    'type': {'code': 'apartment', 'label': 'Appartement'},
    'locality': {'name': 'Almadies', 'slug': 'almadies'},
    'surface_m2': 80,
    'bedrooms': 3,
    'furnished': false,
    'amenities': [
      {'code': 'parking', 'label': 'Parking'},
      {'code': 'security', 'label': 'Gardiennage'},
      {'code': 'ac', 'label': 'Climatisation'},
    ],
    'images': <Object>[],
  },
  'passport': {
    'reference': 'DK-ALM-002458',
    'availability': {
      'state': 'available',
      'confirmed_at': DateTime.now()
          .subtract(const Duration(hours: 5))
          .toUtc()
          .toIso8601String(),
    },
    'checks': [
      {
        'type': 'phone',
        'status': 'passed',
        'performed_at': '2026-10-01T00:00:00Z',
      },
      {
        'type': 'location',
        'status': 'passed',
        'performed_at': '2026-10-08T00:00:00Z',
      },
      {
        'type': 'document',
        'status': 'passed',
        'performed_at': '2026-10-10T00:00:00Z',
      },
    ],
    'notice': 'Un contrôle de document ne garantit pas la propriété juridique du bien.',
  },
  'advertiser': {'name': 'Awa D.', 'account_type': 'individual'},
};

Map<String, dynamic> _inquiry() => {
  'id': 'inq1',
  'status': 'visit_scheduled',
  'role': 'requester',
  'unread': 2,
  'listing': {
    'id': 'l1',
    'reference': 'CV-2026-000101',
    'title': 'Appartement F3 lumineux',
  },
  'last_activity_at': DateTime.now().toUtc().toIso8601String(),
  'can_respond': true,
  'messages': [
    {
      'id': 1,
      'mine': true,
      'body': 'Bonjour, le logement est-il toujours disponible ?',
      'created_at': DateTime.now()
          .subtract(const Duration(hours: 3))
          .toUtc()
          .toIso8601String(),
      'sender_name': 'Moi',
    },
    {
      'id': 2,
      'mine': false,
      'body': 'Oui, vous pouvez le visiter demain matin si vous le souhaitez.',
      'created_at': DateTime.now()
          .subtract(const Duration(hours: 2))
          .toUtc()
          .toIso8601String(),
      'sender_name': 'Awa D.',
    },
  ],
  'visits': [
    {
      'id': 'v1',
      'status': 'proposed',
      'proposed_slots': [
        DateTime.now().add(const Duration(days: 1)).toUtc().toIso8601String(),
      ],
      'scheduled_at': null,
      'proposed_by_me': false,
      'awaiting_me': true,
      'cancel_reason': null,
      'feedback': null,
    },
  ],
};

Map<String, dynamic> _mine(
  String id,
  String status, {
  List<String> missing = const [],
  String? comment,
}) => {
  'id': id,
  'reference': 'CV-2026-0002$id',
  'status': status,
  'transaction': 'rent_long',
  'title': 'Appartement F3 à Yoff',
  'price_xof': 300000,
  'negotiable': false,
  'expires_at': DateTime.now()
      .add(const Duration(days: 20))
      .toUtc()
      .toIso8601String(),
  'missing_fields': missing,
  if (comment != null) 'rejection': {'reason': 'x', 'comment': comment},
  'property': {
    'locality_id': 1,
    'furnished': false,
    'amenity_ids': [1],
    'images': [],
    'surface_m2': 70,
    'bedrooms': 2,
  },
};

http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Future<http.Response> _api(http.Request r) async {
  final p = r.url.path.replaceFirst('/api/v1', '');
  if (p == '/localities') {
    return _json({
      'data': [
        for (final n in ['Almadies', 'Ouakam', 'Yoff', 'Mermoz'])
          {'id': n.length, 'slug': n.toLowerCase(), 'name': n},
      ],
    });
  }
  if (p == '/featured') {
    return _json({
      'data': [_summary(1), _summary(2)],
    });
  }
  if (p == '/listings') {
    return _json({
      'data': [
        for (var i = 0; i < 4; i++)
          _summary(
            i,
            state: ['available', 'to_reconfirm', 'reserved', 'stale'][i],
          ),
      ],
      'meta': {'next_cursor': 'c'},
    });
  }
  if (p.startsWith('/listings/')) return _json({'data': _detail()});
  if (p == '/my/favorites') {
    return _json({
      'data': [
        {'listing': _summary(2), 'available': true},
        {'listing': _summary(3, state: 'to_reconfirm'), 'available': true},
      ],
    });
  }
  if (p == '/my/saved-searches') {
    return _json({
      'data': [
        {
          'id': 's1',
          'name': 'Yoff, 2 chambres',
          'frequency': 'daily',
          'is_active': true,
        },
        {
          'id': 's2',
          'name': 'Almadies ≤ 400 000',
          'frequency': 'immediate',
          'is_active': true,
        },
      ],
    });
  }
  if (p == '/my/inquiries') {
    return _json({
      'data': [
        _inquiry(),
        {
          ..._inquiry(),
          'id': 'inq2',
          'status': 'new',
          'unread': 0,
          'listing': {
            'id': 'l2',
            'reference': 'CV-2026-000102',
            'title': 'Villa avec jardin',
          },
        },
      ],
    });
  }
  if (p == '/inbox') return _json({'data': []});
  if (p.startsWith('/inquiries/')) return _json({'data': _inquiry()});
  if (p == '/my/notifications') {
    return _json({
      'data': [
        {
          'id': 'n1',
          'type': 'visit.proposed',
          'data': {'listing': 'CV-2026-000101'},
          'read': false,
          'created_at': DateTime.now()
              .subtract(const Duration(hours: 1))
              .toUtc()
              .toIso8601String(),
        },
        {
          'id': 'n2',
          'type': 'inquiry.message',
          'data': {'listing': 'CV-2026-000102'},
          'read': true,
          'created_at': DateTime.now()
              .subtract(const Duration(days: 2))
              .toUtc()
              .toIso8601String(),
        },
      ],
      'meta': {'unread': 1},
    });
  }
  if (p == '/my/listings') {
    return _json({
      'data': [
        _mine('1', 'published'),
        _mine('2', 'draft', missing: ['cover_image']),
        _mine('3', 'rejected', comment: 'Photos floues'),
        _mine('4', 'paused'),
      ],
    });
  }
  if (p.startsWith('/my/listings/')) {
    return _json({
      'data': _mine('2', 'draft', missing: ['title', 'cover_image']),
    });
  }
  if (p == '/property-types') {
    return _json({
      'data': [
        {'id': 1, 'label': 'Appartement'},
        {'id': 2, 'label': 'Villa'},
      ],
    });
  }
  if (p == '/amenities') {
    return _json({
      'data': [
        {'id': 1, 'label': 'Parking'},
        {'id': 2, 'label': 'Gardiennage'},
        {'id': 3, 'label': 'Climatisation'},
      ],
    });
  }
  return _json({'data': []});
}

ApiClient _client() => ApiClient(
  tokens: MemoryTokenStore('t'),
  client: MockClient(_api),
  baseUrl: 'http://api.test',
);

Future<void> _shot(
  WidgetTester tester,
  String name,
  Widget screen, {
  double height = 844,
  double width = 390,
  double scale = 1,
  String? dir,
}) async {
  tester.view.physicalSize = Size(width * 2, height * 2);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final key = GlobalKey();
  await tester.pumpWidget(
    RepaintBoundary(
      key: key,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: buildTheme(),
        home: screen,
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.runAsync(() async {
    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: scale);
    final bytes = (await image.toByteData(format: ui.ImageByteFormat.png))!;
    await File('${dir ?? _dir}/$name.png')
        .writeAsBytes(bytes.buffer.asUint8List());
  });
}

void main() {
  setUpAll(() async {
    final loader = FontLoader('Manrope')
      ..addFont(rootBundle.load('assets/fonts/Manrope.ttf'));
    await loader.load();
    // Icônes Material : sans elles, le moteur de test dessine des carrés.
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await icons.load();
  });

  final skip = _dir == null ? 'SHOTS_DIR non défini' : null;

  testWidgets('captures', skip: skip != null, (tester) async {
    final api = _client();
    final listings = ListingsRepository(api);
    final inquiries = InquiriesRepository(api);
    final auth = AuthController(api: api, tokens: MemoryTokenStore('t'));

    await _shot(
      tester,
      '01-accueil',
      HomeScreen(repository: listings, signedIn: false, onSearch: (_) {}),
      height: 1700,
    );
    await _shot(
      tester,
      '02-recherche',
      SearchScreen(repository: listings, signedIn: true, inquiries: inquiries),
    );
    await _shot(
      tester,
      '03-fiche',
      ListingDetailScreen(
        repository: listings,
        reference: 'CV-2026-000101',
        signedIn: true,
        inquiries: inquiries,
      ),
      height: 1900,
    );
    await _shot(
      tester,
      '04-favoris',
      FavoritesScreen(repository: listings, inquiries: inquiries),
    );
    await _shot(tester, '05-alertes', AlertsScreen(repository: listings));
    await _shot(tester, '06-messages', MessagesScreen(repository: inquiries));
    await _shot(
      tester,
      '07-conversation',
      ThreadScreen(repository: inquiries, id: 'inq1'),
    );
    await _shot(
      tester,
      '08-notifications',
      NotificationsScreen(repository: inquiries),
    );
    await _shot(
      tester,
      '09-mes-annonces',
      MyListingsScreen(
        repository: MyListingsRepository(api),
        editor: EditorRepository(api),
      ),
    );
    await _shot(
      tester,
      '10-editeur',
      ListingEditorScreen(repository: EditorRepository(api), id: '2'),
      height: 2300,
    );
    await _shot(
      tester,
      '11-nouvelle-annonce',
      NewListingScreen(repository: EditorRepository(api)),
    );
    await _shot(tester, '12-connexion', LoginScreen(auth: auth));
    await _shot(
      tester,
      '13-inscription',
      RegisterScreen(auth: auth),
      height: 900,
    );
    await _shot(
      tester,
      '14-verification',
      VerifyPhoneScreen(auth: auth, phone: '+221771234567'),
    );
    await _shot(
      tester,
      '15-application',
      AppShell(
        auth: AuthController(api: api, tokens: MemoryTokenStore('t'))
          ..status = AuthStatus.signedOut,
      ),
    );
  });

  // Captures pour les fiches des boutiques d'applications : STORE_DIR=/chemin flutter test test/screenshots
  final storeDir = Platform.environment['STORE_DIR'];
  testWidgets('captures boutiques', skip: storeDir == null, (tester) async {
    final api = _client();
    final listings = ListingsRepository(api);
    final inquiries = InquiriesRepository(api);
    final auth = AuthController(api: api, tokens: MemoryTokenStore('t'));
    final screens = <String, Widget>{
      '1-accueil': HomeScreen(
        repository: listings,
        signedIn: false,
        onSearch: (_) {},
      ),
      '2-recherche': SearchScreen(
        repository: listings,
        signedIn: true,
        inquiries: inquiries,
      ),
      '3-fiche': ListingDetailScreen(
        repository: listings,
        reference: 'CV-2026-000101',
        signedIn: true,
        inquiries: inquiries,
      ),
      '4-messages': ThreadScreen(repository: inquiries, id: 'inq1'),
      '5-annonceur': MyListingsScreen(
        repository: MyListingsRepository(api),
        editor: EditorRepository(api),
      ),
    };
    for (final e in screens.entries) {
      // iPhone 6,7 pouces : 1290 × 2796 ; téléphone Android : 1080 × 1920.
      await _shot(
        tester,
        e.key,
        e.value,
        width: 430,
        height: 932,
        scale: 3,
        dir: '$storeDir/ios-6.7',
      );
      await _shot(
        tester,
        e.key,
        e.value,
        width: 360,
        height: 640,
        scale: 3,
        dir: '$storeDir/android-telephone',
      );
    }
    expect(auth.status, AuthStatus.unknown);
  });
}
