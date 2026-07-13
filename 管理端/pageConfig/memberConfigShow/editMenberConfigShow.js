require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/memberConfigShow/editMenberConfigShow"],
    {
      "07a7": function (t, i, n) {
        "use strict";
        n.r(i);
        var e = n("c21b"),
          o = n("27ab");
        for (var a in o)
          ["default"].indexOf(a) < 0 &&
            (function (t) {
              n.d(i, t, function () {
                return o[t];
              });
            })(a);
        n("d3b8");
        var s = n("828b"),
          r = Object(s.a)(
            o.default,
            e.b,
            e.c,
            !1,
            null,
            null,
            null,
            !1,
            e.a,
            void 0,
          );
        i.default = r.exports;
      },
      "27ab": function (t, i, n) {
        "use strict";
        n.r(i);
        var e = n("dd5d"),
          o = n.n(e);
        for (var a in e)
          ["default"].indexOf(a) < 0 &&
            (function (t) {
              n.d(i, t, function () {
                return e[t];
              });
            })(a);
        i.default = o.a;
      },
      c21b: function (t, i, n) {
        "use strict";
        n.d(i, "b", function () {
          return o;
        }),
          n.d(i, "c", function () {
            return a;
          }),
          n.d(i, "a", function () {
            return e;
          });
        var e = {
            ffBottomLogo: function () {
              return n
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(n.bind(null, "3111"));
            },
            confirmModal: function () {
              return n
                .e("components/confirm-modal/confirm-modal")
                .then(n.bind(null, "4e5b"));
            },
          },
          o = function () {
            var t = this,
              i =
                (t.$createElement,
                t._self._c,
                1 != t.defImage ? t.imgsrc("/static/imgs/shop-del.png") : null),
              n =
                1 == t.defImage
                  ? t.imgsrc("/static/imgs/shop-del-grey.png")
                  : null,
              e = t.__map(t.imglist, function (i, n) {
                return {
                  $orig: t.__get_orig(i),
                  m0: t.imgsrc(i),
                  m1: 0 != n ? t.imgsrc("/static/imgs/shop-move-up.png") : null,
                };
              });
            t.$mp.data = Object.assign({}, { $root: { m2: i, m3: n, l0: e } });
          },
          a = [];
      },
      c3f1: function (t, i, n) {
        "use strict";
        (function (t, i) {
          var e = n("47a9");
          n("86d2"), e(n("3240"));
          var o = e(n("07a7"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = n), i(o.default);
        }).call(this, n("3223").default, n("df3c").createPage);
      },
      cca1: function (t, i, n) {},
      d3b8: function (t, i, n) {
        "use strict";
        var e = n("cca1");
        n.n(e).a;
      },
      dd5d: function (t, i, n) {
        "use strict";
        (function (t) {
          var e = n("47a9");
          Object.defineProperty(i, "__esModule", { value: !0 }),
            (i.default = void 0);
          var o = e(n("7502")),
            a = n("7fc0"),
            s = {
              data: function () {
                return {
                  index: -1,
                  defImage: 0,
                  imglist: [],
                  reqImgList: [],
                  defimg: "",
                  changeIndex: -1,
                };
              },
              components: {
                navigation: function () {
                  n.e("components/navigation/index")
                    .then(
                      function () {
                        return resolve(n("af9e"));
                      }.bind(null, n),
                    )
                    .catch(n.oe);
                },
                hint: function () {
                  n.e("pageConfig/components/top-hint/index")
                    .then(
                      function () {
                        return resolve(n("f250"));
                      }.bind(null, n),
                    )
                    .catch(n.oe);
                },
                confirmModal: function () {
                  n.e("components/confirm-modal/confirm-modal")
                    .then(
                      function () {
                        return resolve(n("4e5b"));
                      }.bind(null, n),
                    )
                    .catch(n.oe);
                },
              },
              methods: {
                initdata: function () {
                  var t = this;
                  (this.imglist = []),
                    (0, a.getsavefaceimage)().then(function (i) {
                      (t.reqImgList = i.data.imglist),
                        null != i.data.imglist && i.data.imglist.length > 0
                          ? ((t.defImage = 0),
                            i.data.imglist.forEach(function (i, n) {
                              t.imglist.push(t.dictVal.uploadURL + i);
                            }))
                          : ((t.defImage = 1),
                            t.imglist.push(
                              t.dictVal.uploadURL + i.data.defImage,
                            )),
                        (t.defimg = t.dictVal.uploadURL + i.data.defImage);
                    });
                },
                del: function (t) {
                  (this.index = t), (this.$refs.confirmModal.show = !0);
                },
                confirm: function () {
                  var t = this,
                    i = {},
                    n = [];
                  this.reqImgList.forEach(function (i, e) {
                    e != t.index && n.push(i);
                  });
                  var e = [];
                  this.imglist.forEach(function (i, n) {
                    n != t.index && e.push(i);
                  }),
                    (i.imglist = n),
                    this.saveImg(i, e, "删除成功！");
                },
                moveUp: function (t) {
                  var i,
                    n = this,
                    e = {},
                    o = [];
                  this.reqImgList.forEach(function (e, a) {
                    a == t - 1
                      ? ((i = n.reqImgList[a]), o.push(n.reqImgList[a + 1]))
                      : a == t
                        ? o.push(i)
                        : o.push(e);
                  });
                  var a = [];
                  this.imglist.forEach(function (e, o) {
                    o == t - 1
                      ? ((i = n.imglist[o]), a.push(n.imglist[o + 1]))
                      : o == t
                        ? a.push(i)
                        : a.push(e);
                  }),
                    (e.imglist = o),
                    this.saveImg(e, a, "上移成功！");
                },
                saveImg: function (i, n, e) {
                  var o = this;
                  (0, a.savefaceimage)(i).then(function (a) {
                    200 == a.code
                      ? ((o.reqImgList = i.imglist),
                        n && i.imglist.length > 0
                          ? (o.imglist = n)
                          : ((o.defImage = 1),
                            (o.imglist = []),
                            o.imglist.push(o.defimg)),
                        t.showToast({ title: e, icon: "none" }))
                      : t.showToast({ title: a.msg, icon: "none" });
                  });
                },
                chooseAvatar: function (i) {
                  if (-1 != i) this.changeIndex = i;
                  else if (this.reqImgList.length > 4)
                    return void t.showToast({
                      title: "最多只可以上传5张！",
                      icon: "none",
                    });
                  this.$u.route({
                    url: "/uview-ui/components/u-avatar-cropper/u-avatar-croppershop",
                    params: {
                      destWidth: 1080,
                      destHeight: 660,
                      rectWidth: 375,
                      rectHeight: 229,
                      fileType: "jpg",
                    },
                  });
                },
              },
              computed: {
                StatusBar: function () {
                  return this.$store.state.systemInfo.statusBarHeight;
                },
                CustomBar: function () {
                  var i = t.getMenuButtonBoundingClientRect();
                  return (
                    i.height +
                    2 * (i.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                    2
                  );
                },
                dictVal: function () {
                  return this.$store.state.dictVal;
                },
              },
              created: function () {
                var i = this;
                t.$on("uAvatarCropper", function (n) {
                  (i.avatar = n),
                    t.uploadFile({
                      url: "".concat(o.default.baseUrl, "/common/uploadfile"),
                      filePath: n,
                      name: "file",
                      complete: function (t) {
                        var n = JSON.parse(t.data).dbUrl,
                          e = JSON.parse(t.data).webUrl;
                        if (-1 != i.changeIndex) {
                          var o = {},
                            a = [];
                          i.reqImgList.forEach(function (t, e) {
                            e == i.changeIndex ? a.push(n) : a.push(t);
                          });
                          var s = [];
                          i.imglist.forEach(function (t, n) {
                            n == i.changeIndex ? s.push(e) : s.push(t);
                          }),
                            (i.imglist = s),
                            (o.imglist = a),
                            (i.changeIndex = -1),
                            i.saveImg(o, i.imglist, "添加成功！");
                        } else {
                          i.reqImgList.push(n);
                          var r = {};
                          1 == i.defImage
                            ? ((i.imglist = []),
                              i.imglist.push(e),
                              (i.defImage = 0))
                            : i.imglist.push(e),
                            (r.imglist = i.reqImgList),
                            i.saveImg(r, i.imglist, "添加成功！");
                        }
                      },
                    });
                });
              },
              onUnload: function () {
                t.$off("uAvatarCropper");
              },
              onLoad: function () {
                this.initdata();
              },
            };
          i.default = s;
        }).call(this, n("df3c").default);
      },
    },
    [["c3f1", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
