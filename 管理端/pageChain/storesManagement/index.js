(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageChain/storesManagement/index"],
  {
    7395: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("d7c4"),
        i = e("d053");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return i[t];
            });
          })(r);
      e("9429");
      var a = e("828b"),
        u = Object(a.a)(
          i.default,
          o.b,
          o.c,
          !1,
          null,
          "c9fe1a90",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = u.exports;
    },
    9275: function (t, n, e) {
      "use strict";
      (function (t, n) {
        var o = e("47a9");
        e("86d2"), o(e("3240"));
        var i = o(e("7395"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(i.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    9429: function (t, n, e) {
      "use strict";
      var o = e("d21d");
      e.n(o).a;
    },
    bdc7: function (t, n, e) {
      "use strict";
      (function (t, o) {
        var i = e("47a9");
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var r = i(e("7eb4")),
          a = i(e("ee10")),
          u = i(e("7ca3")),
          s = e("2d7f");
        function c(t, n) {
          var e = Object.keys(t);
          if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(t);
            n &&
              (o = o.filter(function (n) {
                return Object.getOwnPropertyDescriptor(t, n).enumerable;
              })),
              e.push.apply(e, o);
          }
          return e;
        }
        e("3387");
        var l = {
          components: {
            confirm: function () {
              e.e("components/confirm-modal/confirm-modal")
                .then(
                  function () {
                    return resolve(e("4e5b"));
                  }.bind(null, e),
                )
                .catch(e.oe);
            },
            navigation: function () {
              e.e("components/navigation/index")
                .then(
                  function () {
                    return resolve(e("af9e"));
                  }.bind(null, e),
                )
                .catch(e.oe);
            },
          },
          data: function () {
            return {
              unioninfo: {},
              datalist: [],
              shopNum: 0,
              showTitle: !1,
              delConfirmModal: !1,
              delshow: !1,
              fingerprint: !1,
              delFingerprint: "",
            };
          },
          computed: {
            StatusBar: function () {
              return this.$store.state.systemInfo.statusBarHeight;
            },
            CustomBar: function () {
              var n = t.getMenuButtonBoundingClientRect();
              return (
                n.height +
                2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                2
              );
            },
          },
          methods: {
            hideDown: function () {
              this.datalist.forEach(function (t) {
                t.isShowHandelSelect = !1;
              });
            },
            loadAllStaff: function () {
              var t = this;
              (0, s.getlist)().then(function (n) {
                (n.datalist = n.datalist.map(function (t) {
                  return (function (t) {
                    for (var n = 1; n < arguments.length; n++) {
                      var e = null != arguments[n] ? arguments[n] : {};
                      n % 2
                        ? c(Object(e), !0).forEach(function (n) {
                            (0, u.default)(t, n, e[n]);
                          })
                        : Object.getOwnPropertyDescriptors
                          ? Object.defineProperties(
                              t,
                              Object.getOwnPropertyDescriptors(e),
                            )
                          : c(Object(e)).forEach(function (n) {
                              Object.defineProperty(
                                t,
                                n,
                                Object.getOwnPropertyDescriptor(e, n),
                              );
                            });
                    }
                    return t;
                  })({ isShowHandelSelect: !1 }, t);
                })),
                  (t.datalist = n.datalist),
                  (t.unioninfo = n.unioninfo),
                  (t.shopNum = n.datalist.length);
              });
            },
            creatbrand: function () {
              o.navigateTo({
                url:
                  "/pageChain/brand/index?unLogo=" +
                  this.unioninfo.unLogo +
                  "&unName=" +
                  this.unioninfo.unName +
                  "&fullUnLogo=" +
                  this.unioninfo.fullUnLogo,
              });
            },
            editHome: function (t) {
              o.navigateTo({
                url: "/pagesImp/shop/setting/store/store-setting?id=" + t,
              });
            },
            handleCreated: function () {
              this.shopNum >= 20
                ? (this.$refs.confirmModal.show = !0)
                : this.unioninfo && null != this.unioninfo.unName
                  ? o.navigateTo({
                      url: "/pagesImp/shop/setting/store/store-setting?id=storesManagement",
                    })
                  : (this.$refs.addconfirmModal.show = !0);
            },
            handleCancelbtn: function () {
              (this.$refs.confirmModal.show = !1),
                (this.$refs.addconfirmModal.show = !1);
            },
            confirmDel: function () {
              this.delshow = !0;
            },
            handleSubmit: function () {
              var n = this;
              (n.delshow = !1),
                t.checkIsSupportSoterAuthentication({
                  success: function (e) {
                    if (0 != e.supportMode.length) {
                      var o;
                      (o =
                        2 == e.supportMode.length ||
                        e.supportMode.includes("fingerPrint")
                          ? "fingerPrint"
                          : "facial"),
                        n.handleFingerprint(o);
                    } else
                      t.showToast({
                        icon: "none",
                        title: "本机暂不支持生物识别",
                      });
                  },
                  fail: function (n) {
                    t.showToast({
                      icon: "none",
                      title: "本机暂不支持生物识别",
                    });
                  },
                });
            },
            handleFingerprint: function (n) {
              var e = this;
              t.startSoterAuthentication({
                requestAuthModes: [n],
                authContent:
                  "fingerPrint" === n ? "请用指纹解锁" : "请用面部识别",
                challenge: "123456",
                success: function (n) {
                  (0, s.delShop)({ siteId: e.delFingerprint }).then(
                    function (n) {
                      200 == n.code &&
                        ((e.delshow = !1),
                        (e.datalist = n.datalist),
                        t.showToast({ icon: "none", title: "删除成功" }),
                        setTimeout(function () {
                          e.loadAllStaff();
                        }, 1e3));
                    },
                  );
                },
                fail: function (n) {
                  t.showToast({ icon: "none", title: "识别失败" });
                },
              });
            },
            handleHome: function (t) {
              var n = this;
              return (0, a.default)(
                r.default.mark(function e() {
                  var o;
                  return r.default.wrap(function (e) {
                    for (;;)
                      switch ((e.prev = e.next)) {
                        case 0:
                          (o = t.currentTarget.dataset.index.siteId),
                            n.$store
                              .dispatch("getStopInfo", { siteid: o })
                              .then(function (t) {
                                n.href({
                                  url: "/pages/home/home",
                                  openType: "reLaunch",
                                });
                              });
                        case 2:
                        case "end":
                          return e.stop();
                      }
                  }, e);
                }),
              )();
            },
            handleDelect: function (t) {
              (this.delFingerprint = t.currentTarget.dataset.id.siteId),
                (this.$refs.delConfirmModal.show = !0);
            },
            handleCut: function (t) {
              var n = this,
                e = t.currentTarget.dataset.index.siteId;
              this.$store.dispatch("getStopInfo", e).then(function (t) {
                n.loadAllStaff(), (n.showTitle = !1);
              });
            },
            handleShowTitle: function (t) {
              var n = this.datalist[t].isShowHandelSelect;
              this.datalist.forEach(function (t) {
                return (t.isShowHandelSelect = !1);
              }),
                (this.datalist[t].isShowHandelSelect = !n);
            },
          },
          onShow: function () {
            this.loadAllStaff();
          },
        };
        n.default = l;
      }).call(this, e("df3c").default, e("3223").default);
    },
    d053: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("bdc7"),
        i = e.n(o);
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(r);
      n.default = i.a;
    },
    d21d: function (t, n, e) {},
    d7c4: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return i;
      }),
        e.d(n, "c", function () {
          return r;
        }),
        e.d(n, "a", function () {
          return o;
        });
      var o = {
          uIcon: function () {
            return e
              .e("uview-ui/components/u-icon/u-icon")
              .then(e.bind(null, "81af"));
          },
          confirmModal: function () {
            return e
              .e("components/confirm-modal/confirm-modal")
              .then(e.bind(null, "4e5b"));
          },
          ffPopup: function () {
            return e
              .e("components/ff-popup/ff-popup")
              .then(e.bind(null, "c29b"));
          },
          uButton: function () {
            return e
              .e("uview-ui/components/u-button/u-button")
              .then(e.bind(null, "d5d3"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        i = function () {
          var t = this,
            n =
              (t.$createElement,
              t._self._c,
              t.imgsrc("/unioncard/stores-management-edit.png")),
            e = t.imgsrc("/static/imgs/current_stadium.png"),
            o = t.imgsrc("/static/imgs/triangle_02.png"),
            i = t.imgsrc("/unioncard/home_edit.png"),
            r = t.__map(t.datalist, function (n, e) {
              return {
                $orig: t.__get_orig(n),
                m3: n.iscurrent ? null : t.imgsrc("/unioncard/home_index.png"),
                m5: n.iscurrent ? null : t.imgsrc("/static/imgs/del.png"),
              };
            }),
            a = t.imgsrc("/static/imgs/importantNote.png");
          t.$mp.data = Object.assign(
            {},
            { $root: { m0: n, m1: e, m2: o, m4: i, l0: r, m6: a } },
          );
        },
        r = [];
    },
  },
  [["9275", "common/runtime", "common/vendor"]],
]);
