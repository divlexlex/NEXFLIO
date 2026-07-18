import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../utils/theme.dart';
import '../../utils/page_transitions.dart';
import '../../models/product_model.dart';
import '../../services/api_service.dart';
import '../../widgets/app_network_image.dart';
import '../catalog/catalog_detail_screen.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  List<ProductModel> _products = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/products');
      final products = (data as List)
          .map((e) => ProductModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _products = products;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load products.';
        _isLoading = false;
      });
    }
  }

  void _openDetail(ProductModel product) {
    Navigator.push(
      context,
      fadeSlideRoute(CatalogDetailScreen(
        type: 'product',
        id: product.id,
        initialTitle: product.name,
        initialImageUrl: product.imageUrl,
        initialPrice: product.price,
        initialSubtitle: product.category,
      )),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.appBg,
      appBar: AppBar(title: const Text('Shop Products')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: kPrimaryColor));
    }
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, style: TextStyle(color: context.appText)),
            const SizedBox(height: 12),
            ElevatedButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: kPrimaryColor,
      child: _products.isEmpty
          ? ListView(
              children: [
                const SizedBox(height: 140),
                Icon(Icons.shopping_bag_outlined,
                    size: 64, color: kPrimaryColor.withOpacity(0.5)),
                const SizedBox(height: 12),
                Center(
                  child: Text('No products yet.',
                      style: TextStyle(color: context.appText)),
                ),
              ],
            )
          : GridView.builder(
              padding: const EdgeInsets.all(kDefaultPadding),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 14,
                mainAxisSpacing: 14,
                childAspectRatio: 0.72,
              ),
              itemCount: _products.length,
              itemBuilder: (context, i) => _buildCard(_products[i]),
            ),
    );
  }

  Widget _buildCard(ProductModel product) {
    return GestureDetector(
      onTap: () => _openDetail(product),
      child: Container(
        decoration: BoxDecoration(
          color: context.appSurface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: context.appBorder),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: AppNetworkImage(
                url: product.imageUrl,
                width: double.infinity,
                placeholderIcon: Icons.shopping_bag_outlined,
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: context.appText,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  if (product.category != null)
                    Text(
                      product.category!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: context.appSubtle, fontSize: 12),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    product.formattedPrice,
                    style: const TextStyle(
                      color: kPrimaryColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
