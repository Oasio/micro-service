from rest_framework.test import APITestCase

from .models import Product


class ProductAPITests(APITestCase):
    def setUp(self):
        self.product = Product.objects.create(
            name='Casque XYZ', price='89.90', stock=10, category='electronics'
        )

    def test_list_products(self):
        response = self.client.get('/api/v1/products/')
        self.assertEqual(response.status_code, 200)
        self.assertIn('results', response.data)
        self.assertEqual(response.data['count'], 1)

    def test_create_product(self):
        data = {'name': 'Souris', 'price': '29.99', 'stock': 50}
        response = self.client.post('/api/v1/products/', data, format='json')
        self.assertEqual(response.status_code, 201)
        self.assertEqual(Product.objects.count(), 2)

    def test_get_nonexistent_returns_404(self):
        response = self.client.get('/api/v1/products/99999/')
        self.assertEqual(response.status_code, 404)

    def test_create_negative_price_rejected(self):
        data = {'name': 'Erreur', 'price': '-5.00', 'stock': 1}
        response = self.client.post('/api/v1/products/', data, format='json')
        self.assertEqual(response.status_code, 400)
        self.assertIn('price', response.data)

    def test_delete_returns_204(self):
        response = self.client.delete(f'/api/v1/products/{self.product.id}/')
        self.assertEqual(response.status_code, 204)
        self.assertEqual(Product.objects.count(), 0)

    def test_search_filter(self):
        Product.objects.create(name='Clavier', price='49.00', stock=5)
        response = self.client.get('/api/v1/products/?search=casque')
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.data['count'], 1)
