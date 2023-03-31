<template>
    <inertia-link :href="href" :class="{'router-link-exact-active': active, 'router-link-active': active }">
        <slot/>
    </inertia-link>
</template>

<script>
export default {
    name: "RouterLink",
    props: {
        to: Object|String
    },
    computed: {
        href(){
            if(typeof this.to === 'string'){
                return this.to
            } else {
                let name = this.to.name
                return this.route(name)
            }
        },
        active() {
            return this.href === this.$page.url
        }
    }
}
</script>

<style scoped>

</style>
