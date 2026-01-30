import 'package:url_launcher/url_launcher.dart';
import 'package:flutter/material.dart';
import 'package:tabungan/utils/custom_toast.dart';

/// Utility untuk membuka semua jenis URL dengan proper error handling
class URLLauncherUtil {
  /// Buka URL web biasa
  static Future<void> openURL(BuildContext context, String url) async {
    try {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
        print('DEBUG: Opened URL: $url');
      } else {
        CustomToast.error(context, 'Tidak dapat membuka URL');
        print('DEBUG: Cannot launch URL: $url');
      }
    } catch (e) {
      CustomToast.error(context, 'Error: $e');
      print('DEBUG: Error opening URL: $e');
    }
  }

  /// Buka WhatsApp dengan dual-mode (Intent + Fallback)
  static Future<void> openWhatsApp(
    BuildContext context, {
    required String phoneNumber,
    required String message,
  }) async {
    try {
      final encodedMessage = Uri.encodeComponent(message);

      // Android: Gunakan intent scheme untuk WhatsApp
      final whatsappUrl =
          "whatsapp://send?phone=$phoneNumber&text=$encodedMessage";
      final whatsappUri = Uri.parse(whatsappUrl);

      // Fallback ke wa.me jika intent tidak bekerja
      final webUrl = "https://wa.me/$phoneNumber?text=$encodedMessage";
      final webUri = Uri.parse(webUrl);

      print('DEBUG: Trying WhatsApp intent: $whatsappUrl');

      try {
        if (await canLaunchUrl(whatsappUri)) {
          print('DEBUG: WhatsApp intent available, launching...');
          await launchUrl(whatsappUri, mode: LaunchMode.externalApplication);
          print('DEBUG: WhatsApp opened via intent');
          return;
        }
      } catch (e) {
        print('DEBUG: Intent failed: $e, trying web URL...');
      }

      // Fallback ke web URL
      print('DEBUG: Trying web URL: $webUrl');
      if (await canLaunchUrl(webUri)) {
        print('DEBUG: Web URL valid, launching...');
        await launchUrl(webUri, mode: LaunchMode.externalApplication);
        print('DEBUG: WhatsApp opened via web');
      } else {
        print('DEBUG: Cannot open WhatsApp');
        CustomToast.error(
          context,
          'WhatsApp tidak terinstall. Silakan install WhatsApp terlebih dahulu.',
        );
      }
    } catch (e) {
      print('DEBUG: Error: $e');
      CustomToast.error(context, 'Gagal membuka WhatsApp: $e');
    }
  }

  /// Buka Play Store untuk rating app
  static Future<void> openPlayStore(
    BuildContext context,
    String packageId,
  ) async {
    try {
      // Android intent untuk Play Store
      final playStoreUrl =
          'https://play.google.com/store/apps/details?id=$packageId';
      final playStoreUri = Uri.parse(playStoreUrl);

      print('DEBUG: Opening Play Store: $playStoreUrl');

      if (await canLaunchUrl(playStoreUri)) {
        await launchUrl(playStoreUri, mode: LaunchMode.externalApplication);
        print('DEBUG: Play Store opened');
      } else {
        CustomToast.error(context, 'Tidak dapat membuka Play Store');
        print('DEBUG: Cannot launch Play Store');
      }
    } catch (e) {
      print('DEBUG: Error opening Play Store: $e');
      CustomToast.error(context, 'Error: $e');
    }
  }

  /// Buka social media dengan proper handling
  static Future<void> openSocialMedia(
    BuildContext context, {
    required String platform, // facebook, instagram, twitter
    required String handle,
  }) async {
    try {
      late String url;

      // Buat URL berdasarkan platform
      switch (platform.toLowerCase()) {
        case 'facebook':
          url = 'https://www.facebook.com/$handle';
          break;
        case 'instagram':
          url = 'https://www.instagram.com/$handle';
          break;
        case 'twitter':
        case 'x':
          url = 'https://x.com/$handle';
          break;
        default:
          url = handle; // Fallback ke URL langsung jika tidak dikenali
      }

      final uri = Uri.parse(url);
      print('DEBUG: Opening $platform: $url');

      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
        print('DEBUG: $platform opened');
      } else {
        CustomToast.error(context, 'Tidak dapat membuka $platform');
        print('DEBUG: Cannot launch $platform');
      }
    } catch (e) {
      print('DEBUG: Error opening social media: $e');
      CustomToast.error(context, 'Error: $e');
    }
  }

  /// Buka email
  static Future<void> openEmail(
    BuildContext context, {
    required String email,
    String subject = '',
    String body = '',
  }) async {
    try {
      final emailUri = Uri(
        scheme: 'mailto',
        path: email,
        queryParameters: {
          if (subject.isNotEmpty) 'subject': subject,
          if (body.isNotEmpty) 'body': body,
        },
      );

      print('DEBUG: Opening email: $email');

      if (await canLaunchUrl(emailUri)) {
        await launchUrl(emailUri);
        print('DEBUG: Email opened');
      } else {
        CustomToast.error(context, 'Tidak dapat membuka email');
        print('DEBUG: Cannot launch email');
      }
    } catch (e) {
      print('DEBUG: Error opening email: $e');
      CustomToast.error(context, 'Error: $e');
    }
  }

  /// Buka telepon
  static Future<void> openPhone(
    BuildContext context,
    String phoneNumber,
  ) async {
    try {
      final phoneUri = Uri(scheme: 'tel', path: phoneNumber);
      print('DEBUG: Opening phone: $phoneNumber');

      if (await canLaunchUrl(phoneUri)) {
        await launchUrl(phoneUri);
        print('DEBUG: Phone opened');
      } else {
        CustomToast.error(context, 'Tidak dapat membuka telepon');
        print('DEBUG: Cannot launch phone');
      }
    } catch (e) {
      print('DEBUG: Error opening phone: $e');
      CustomToast.error(context, 'Error: $e');
    }
  }
}
