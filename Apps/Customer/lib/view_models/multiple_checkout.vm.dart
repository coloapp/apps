import 'package:flutter/material.dart';
import 'package:fuodz/constants/app_routes.dart';
import 'package:fuodz/models/checkout.dart';
import 'package:fuodz/models/vendor.dart';
import 'package:fuodz/services/alert.service.dart';
import 'package:fuodz/services/cart.service.dart';
import 'package:fuodz/services/checkout.service.dart';
import 'package:fuodz/view_models/checkout_base.vm.dart';
import 'package:localize_and_translate/localize_and_translate.dart';
import 'package:dartx/dartx.dart';

class MultipleCheckoutViewModel extends CheckoutBaseViewModel {
  List<Vendor> vendors = [];
  List<Map<String, dynamic>> orderData = [];
  double totalTax = 0;
  double totalDeliveryFee = 0;
  List<double> taxes = [];
  List<double> vendorFees = [];
  List<double> subtotals = [];

  MultipleCheckoutViewModel(BuildContext context, CheckOut checkout) {
    this.viewContext = context;
    this.checkout = checkout;
  }

  //
  void initialise() async {
    super.initialise();
    fetchVendorsDetails();

    //
    updateTotalOrderSummary();
  }

  //
  fetchVendorsDetails() async {
    //
    vendors = CartServices.productsInCart
        .map((e) => e.product!.vendor)
        .toList()
        .toSet()
        .toList();

    vendors = vendors.distinctBy((model) => model.id).toList();
    //
    setBusy(true);
    try {
      for (var i = 0; i < vendors.length; i++) {
        vendors[i] = await vendorRequest.vendorDetails(
          vendors[i].id,
          params: {
            "type": "brief",
          },
        );
      }
    } catch (error) {
      print("Error Getting Vendor Details ==> $error");
    }
    setBusy(false);
  }

  //update total/order amount summary
  @override
  updateTotalOrderSummary() async {
    //clear previous data
    checkout?.tax = 0;
    checkout?.deliveryFee = 0;
    orderData = [];
    totalTax = 0;
    totalDeliveryFee = 0;
    taxes = [];
    vendorFees = [];
    subtotals = [];

    //delivery fee
    if (!isPickup && !delievryAddressOutOfRange) {
      //selected delivery address is within range
      //vendor charges per km
      setBusy(true);

      //
      for (var i = 0; i < vendors.length; i++) {
        //
        final mVendor = vendors[i];
        double mDeliveryFee = 0.0;

        //
        try {
          double orderDeliveryFee = await checkoutRequest.orderSummary(
            deliveryAddressId: deliveryAddress!.id!,
            vendorId: mVendor.id,
          );

          //adding base fee
          mDeliveryFee += orderDeliveryFee;
        } catch (error) {
          if (mVendor.chargePerKm == 1 && deliveryAddress != null) {
            mDeliveryFee += mVendor.deliveryFee * deliveryAddress!.distance!;
          } else {
            mDeliveryFee += mVendor.deliveryFee;
          }

          //adding base fee
          mDeliveryFee += mVendor.baseDeliveryFee;
        }
        updateOrderData(mVendor, deliveryFee: mDeliveryFee);
        //
      }

      //
      setBusy(false);
    } else {
      checkout?.deliveryFee = 0.00;

      for (var i = 0; i < vendors.length; i++) {
        final mVendor = vendors[i];
        updateOrderData(mVendor);
        //
      }
    }

    //handle coupon for delivery
    if (checkout!.coupon != null && checkout!.coupon!.for_delivery) {
      checkout!.discount = CheckoutService.generateOrderDiscount(
        checkout!.coupon,
        checkout!.subTotal,
        checkout!.deliveryFee,
      );
    }
    //total tax number
    totalTax = taxes.sum();
    checkout!.tax = totalTax;
    checkout!.subTotal = subtotals.sum();
    //total
    checkout!.total = (checkout!.subTotal - checkout!.discount) +
        totalDeliveryFee +
        checkout!.tax;
    //totalfees
    checkout!.total += vendorFees.sum();
    //
    updateCheckoutTotalAmount();
    updatePaymentOptionSelection();
    notifyListeners();
  }

//calcualte for each vendor and prepare jsonobject for checkout
  updateOrderData(Vendor mVendor, {double deliveryFee = 0.00}) {
    //tax
    double calTax = ((double.tryParse(mVendor.tax) ?? 0) / 100);
    double vendorSubtotal = CartServices.vendorSubTotal(mVendor.id);
    calTax = calTax * vendorSubtotal;
    checkout!.tax += calTax;
    totalTax += double.tryParse(mVendor.tax) ?? 0;
    totalDeliveryFee += deliveryFee;
    taxes.add(calTax);
    subtotals.add(vendorSubtotal);

    //
    double vendorDiscount = 0.00;
    if (checkout?.coupon != null) {
      vendorDiscount = CartServices.vendorOrderDiscount(
        mVendor.id,
        checkout!.coupon!,
      );
    }
    //total amount for that single order
    double vendorTotal =
        (vendorSubtotal - vendorDiscount) + deliveryFee + calTax;

    //fees
    List<Map> feesObjects = [];
    double totalVendorFees = 0;
    for (var fee in mVendor.fees) {
      double calFee = 0;
      String feeName = fee.name ?? "Fee".tr();
      if (fee.isPercentage) {
        calFee = fee.getRate(vendorSubtotal);
        feeName = "$feeName (${fee.value}%)";
      } else {
        calFee = fee.value;
      }

      //
      feesObjects.add({
        "id": fee.id,
        "name": feeName,
        "amount": calFee,
      });
      //add to total
      totalVendorFees += calFee;
      vendorTotal += calFee;
    }

    //vendor total fee
    vendorFees.add(totalVendorFees);

    //
    final orderObject = {
      "vendor_id": mVendor.id,
      "delivery_fee": deliveryFee,
      "tax": calTax,
      "sub_total": vendorSubtotal,
      "discount": vendorDiscount,
      "tip": 0,
      "total": vendorTotal,
      "fees": feesObjects,
    };

    //prepare order data
    final orderDataIndex = orderData.indexWhere(
      (e) => e.containsKey("vendor_id") && e["vendor_id"] == mVendor.id,
    );
    if (orderDataIndex >= 0) {
      orderData[orderDataIndex] = orderObject;
    } else {
      orderData.add(orderObject);
    }
  }

//
  @override
  processOrderPlacement() async {
    //process the order placement
    setBusy(true);

    try {
      //prepare order data
      List<Map<String, dynamic>> vendorsOrderData = [];
      orderData.forEach((e) {
        Map<String, dynamic> vendorOrderData = {};
        vendorOrderData.addAll(e);
        vendorOrderData.addAll(
          {
            "products": CartServices.multipleVendorOrderPayload(e["vendor_id"]),
          },
        );

        //
        vendorsOrderData.add(vendorOrderData);
      });

      //set the total with discount as the new total
      checkout!.total = checkout!.totalWithTip;
      //
      final apiResponse = await checkoutRequest.newMultipleVendorOrder(
        checkout!,
        tip: driverTipTEC.text,
        note: noteTEC.text,
        payload: {
          "data": vendorsOrderData,
        },
      );
      //not error
      if (apiResponse.allGood) {
        //any payment
        await AlertService.success(
          title: "Checkout".tr(),
          text: apiResponse.message,
        );
        showOrdersTab(context: viewContext);
        if (Navigator.canPop(viewContext)) {
          Navigator.of(viewContext).popUntil(
            (route) {
              return route.settings.name == AppRoutes.homeRoute ||
                  route.isFirst;
            },
          );
        }
      } else {
        await AlertService.error(
          title: "Checkout".tr(),
          text: apiResponse.message,
        );
      }
    } catch (error) {
      print("Error Placing Order ==> $error");
      toastError("$error");
    }
    setBusy(false);
  }
}
