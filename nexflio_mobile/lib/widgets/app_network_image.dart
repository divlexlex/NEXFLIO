import 'package:flutter/material.dart';
import '../utils/constants.dart';

/// Network image with a graceful placeholder when the URL is null, still
/// loading, or fails to load. Used for services, products, and promos so a
/// missing image never breaks the layout.
class AppNetworkImage extends StatelessWidget {
  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final IconData placeholderIcon;

  const AppNetworkImage({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholderIcon = Icons.spa_outlined,
  });

  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) {
      return _placeholder();
    }
    return Image.network(
      url!,
      width: width,
      height: height,
      fit: fit,
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return _placeholder(loading: true);
      },
      errorBuilder: (context, error, stack) => _placeholder(),
    );
  }

  Widget _placeholder({bool loading = false}) {
    return Container(
      width: width,
      height: height,
      color: kSecondaryColor,
      alignment: Alignment.center,
      child: loading
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2, color: kPrimaryColor),
            )
          : Icon(placeholderIcon, color: kPrimaryColor.withOpacity(0.5), size: 36),
    );
  }
}
